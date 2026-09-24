<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\SchoolKnowledgeChunk;
use App\Models\SchoolKnowledgeSource;
use App\Models\User;
use App\Services\Guardrail\RegexPiiFilter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Shared, administrator-managed RAG reference for the whole school.
 *
 * Unlike Agent knowledge, a source here never belongs to an individual user.
 * It is only added as untrusted reference material after the workplace policy.
 */
class SchoolKnowledgeService
{
    public const MAX_UPLOAD_FILES = 10;

    public const MAX_TOTAL_UPLOAD_BYTES = 25 * 1024 * 1024;

    /**
     * Every public page currently nested under Introduction in the official
     * English LSTS menu (School, Campuses, and Curriculum).
     *
     * @var array<int, string>
     */
    public const INTRODUCTION_LSTS_URLS = [
        'https://lsts.edu.vn/en/school/purpose-of-establishment',
        'https://lsts.edu.vn/en/school/milestones-of-development',
        'https://lsts.edu.vn/en/school/core-values',
        'https://lsts.edu.vn/en/school/mission-and-vision',
        'https://lsts.edu.vn/en/school/education-quality-accreditation',
        'https://lsts.edu.vn/en/campus/middle-school-campus',
        'https://lsts.edu.vn/en/campus/high-school-campus',
        'https://lsts.edu.vn/en/curriculum/the-capstone-program',
        'https://lsts.edu.vn/en/curriculum/the-curriculum-structure',
        'https://lsts.edu.vn/en/curriculum/core-program-2',
        'https://lsts.edu.vn/en/curriculum/extension-program',
        'https://lsts.edu.vn/en/curriculum/development-program',
        'https://lsts.edu.vn/en/byod',
    ];

    /**
     * Official Vietnamese sources corresponding to the stable, foundational
     * school information. The Vietnamese menu is not a literal one-to-one
     * mirror of English, so it also includes its official accreditation page.
     *
     * @var array<int, string>
     */
    public const VIETNAMESE_BASIC_URLS = [
        'https://lsts.edu.vn',
        'https://lsts.edu.vn/tuyen-sinh',
        'https://lsts.edu.vn/nha-truong/muc-dich-thanh-lap',
        'https://lsts.edu.vn/nha-truong/qua-trinh-phat-trien',
        'https://lsts.edu.vn/nha-truong/gia-tri-cot-loi',
        'https://lsts.edu.vn/nha-truong/su-menh-va-tam-nhin',
        'https://lsts.edu.vn/nha-truong/hieu-ca',
        'https://lsts.edu.vn/co-so/co-so-thcs',
        'https://lsts.edu.vn/co-so/co-so-thpt',
        'https://lsts.edu.vn/chuong-trinh/khung-chuong-trinh-captone',
        'https://lsts.edu.vn/chuong-trinh/cau-truc-chuong-trinh-giao-duc-lsts',
        'https://lsts.edu.vn/chuong-trinh/chuong-trinh-cot-loi',
        'https://lsts.edu.vn/chuong-trinh/chuong-trinh-mo-rong',
        'https://lsts.edu.vn/chuong-trinh/chuong-trinh-phat-trien',
        'https://lsts.edu.vn/byod',
        'https://lsts.edu.vn/cong-khai/kiem-dinh-chat-luong-giao-duc/ket-qua-kiem-dinh-chat-luong-giao-duc-theo-tieu-chuan-cua-bo-giao-duc-va-dao-tao',
    ];

    /** @var array<int, string> */
    public const UPLOAD_EXTENSIONS = ['txt', 'csv', 'html', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    public function __construct(
        private readonly KnowledgeService $knowledgeService,
        private readonly EmbeddingService $embeddingService,
        private readonly RegexPiiFilter $piiFilter,
        private readonly WebPageReaderService $webPageReader,
    ) {
    }

    public function isEnabled(): bool
    {
        return AppSetting::boolean('school_knowledge_enabled', true);
    }

    public function storeUpload(UploadedFile $file, User $admin): SchoolKnowledgeSource
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, self::UPLOAD_EXTENSIONS, true)) {
            throw new RuntimeException('This file type is not supported for School Knowledge Base.');
        }

        $path = 'school-knowledge/'.Str::uuid().'.'.$extension;
        Storage::disk('knowledge')->put($path, $file->get());

        $source = SchoolKnowledgeSource::create([
            'created_by' => $admin->id,
            'type' => SchoolKnowledgeSource::TYPE_UPLOAD,
            'title' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'status' => 'processing',
        ]);

        $this->indexUpload($source);

        return $source->fresh();
    }

    public function syncWebsite(string $url, User $admin): SchoolKnowledgeSource
    {
        $url = trim($url);
        if (! $this->isOfficialLstsUrl($url)) {
            throw new RuntimeException('Only public pages on lsts.edu.vn can be added to the School Knowledge Base.');
        }

        $source = SchoolKnowledgeSource::firstOrCreate(
            ['type' => SchoolKnowledgeSource::TYPE_WEBSITE, 'source_url' => $url],
            [
                'created_by' => $admin->id,
                'title' => $this->websiteTitle($url),
                'status' => 'processing',
            ],
        );

        $source->update(['created_by' => $admin->id, 'status' => 'processing', 'failure_reason' => null]);
        $text = $this->webPageReader->readUrl($url);

        if (str_starts_with($text, '[Web page unavailable:')) {
            $source->update(['status' => 'failed', 'failure_reason' => Str::limit($text, 1000)]);

            throw new RuntimeException('The page could not be read. Check that it is a public LSTS page and try again.');
        }

        $source->update(['title' => $this->websiteTitle($url)]);
        $this->indexText($source, $text);

        return $source->fresh();
    }

    /** @return array{indexed: int, failed: array<int, string>} */
    public function importBasicSchoolInformation(User $admin): array
    {
        return $this->importWebsiteSources(
            array_values(array_unique([...self::INTRODUCTION_LSTS_URLS, ...self::VIETNAMESE_BASIC_URLS])),
            $admin,
            'LSTS basic school information page',
        );
    }

    /**
     * @param array<int, string> $urls
     * @return array{indexed: int, failed: array<int, string>}
     */
    private function importWebsiteSources(array $urls, User $admin, string $logLabel): array
    {
        $indexed = 0;
        $failed = [];

        foreach ($urls as $url) {
            try {
                $this->syncWebsite($url, $admin);
                $indexed++;
            } catch (Throwable $exception) {
                Log::notice($logLabel.' could not be indexed', [
                    'host' => parse_url($url, PHP_URL_HOST),
                    'path' => parse_url($url, PHP_URL_PATH),
                    'exception' => $exception::class,
                ]);
                $failed[] = $url;
            }
        }

        return compact('indexed', 'failed');
    }

    public function reindex(SchoolKnowledgeSource $source): SchoolKnowledgeSource
    {
        if ($source->type === SchoolKnowledgeSource::TYPE_WEBSITE) {
            if (! $source->source_url || ! $this->isOfficialLstsUrl($source->source_url)) {
                throw new RuntimeException('This website source is no longer an approved LSTS URL.');
            }

            return $this->syncWebsite($source->source_url, $source->creator ?? User::query()->findOrFail($source->created_by));
        }

        $this->indexUpload($source);

        return $source->fresh();
    }

    public function delete(SchoolKnowledgeSource $source): void
    {
        if ($source->storage_path) {
            Storage::disk('knowledge')->delete($source->storage_path);
        }

        $source->delete();
    }

    public function retrieveContext(string $query, ?int $topK = null): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $topK ??= (int) config('openai.rag_top_k', 4);
        $chunks = SchoolKnowledgeChunk::query()
            ->with('source:id,title,status')
            ->whereHas('source', fn ($source) => $source->where('status', 'ready'))
            ->get();

        if ($chunks->isEmpty()) {
            return '';
        }

        $queryEmbedding = $this->embeddingService->embed($query);
        $semanticChunks = $chunks->filter(fn (SchoolKnowledgeChunk $chunk) => $chunk->embedding !== []);
        if ($queryEmbedding !== [] && $semanticChunks->isNotEmpty()) {
            $ranked = $semanticChunks->map(fn (SchoolKnowledgeChunk $chunk) => [
                'chunk' => $chunk,
                'score' => $this->embeddingService->cosineSimilarity($queryEmbedding, $chunk->embedding),
            ])->sortByDesc('score')->take($topK);
        } else {
            $ranked = $this->rankKeywordChunks($chunks, $query, $topK);
        }

        if ($ranked->isEmpty()) {
            return '';
        }

        $parts = $ranked->map(function (array $item): string {
            /** @var SchoolKnowledgeChunk $chunk */
            $chunk = $item['chunk'];
            $title = Str::limit((string) ($chunk->source?->title ?? 'School source'), 180);

            return "[School source: {$title}]\n{$chunk->content}\n[/School source]";
        })->values()->all();

        return "=== SCHOOL KNOWLEDGE BASE (retrieved reference material) ===\n\n".implode("\n\n", $parts);
    }

    private function indexUpload(SchoolKnowledgeSource $source): void
    {
        if (! $source->storage_path || ! Storage::disk('knowledge')->exists($source->storage_path)) {
            $source->update(['status' => 'failed', 'failure_reason' => 'The uploaded file is no longer available.']);
            throw new RuntimeException('The uploaded file is no longer available.');
        }

        $extension = strtolower(pathinfo($source->storage_path, PATHINFO_EXTENSION));
        $text = $this->knowledgeService->extractTextFromBinary(
            Storage::disk('knowledge')->get($source->storage_path),
            $extension,
        );

        if (trim($text) === '') {
            $source->update([
                'status' => 'failed',
                'failure_reason' => 'No readable text could be extracted from this file.',
            ]);
            throw new RuntimeException('No readable text could be extracted from this file. For scanned PDFs, upload a text-readable version.');
        }

        $this->indexText($source, $text);
    }

    private function indexText(SchoolKnowledgeSource $source, string $text): void
    {
        $source->update(['status' => 'processing', 'failure_reason' => null]);
        SchoolKnowledgeChunk::where('school_knowledge_source_id', $source->id)->delete();

        try {
            $text = $this->piiFilter->filter($text)['filtered'];
            $chunks = $this->knowledgeService->chunkText(
                $text,
                (int) config('openai.rag_chunk_chars'),
                (int) config('openai.rag_chunk_overlap'),
            );

            if ($chunks === []) {
                throw new RuntimeException('No indexable text was found in this source.');
            }

            $embeddings = $this->embeddingService->embedBatch($chunks);
            $hasEmbeddings = count($embeddings) === count($chunks)
                && collect($embeddings)->every(fn ($embedding) => is_array($embedding) && $embedding !== []);

            foreach ($chunks as $index => $chunk) {
                SchoolKnowledgeChunk::create([
                    'school_knowledge_source_id' => $source->id,
                    'chunk_index' => $index,
                    'content' => $chunk,
                    'embedding' => $hasEmbeddings ? $embeddings[$index] : [],
                ]);
            }

            $source->update([
                'status' => 'ready',
                'failure_reason' => null,
                'last_synced_at' => now(),
            ]);

            Log::info('School Knowledge source indexed', [
                'source_id' => $source->id,
                'type' => $source->type,
                'chunk_count' => count($chunks),
                'strategy' => $hasEmbeddings ? 'semantic' : 'keyword',
            ]);
        } catch (Throwable $exception) {
            $source->update(['status' => 'failed', 'failure_reason' => Str::limit($exception->getMessage(), 1000)]);
            Log::warning('School Knowledge source indexing failed', ['source_id' => $source->id, 'exception' => $exception::class]);

            throw $exception;
        }
    }

    private function isOfficialLstsUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $scheme === 'https' && in_array($host, ['lsts.edu.vn', 'www.lsts.edu.vn'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }

    private function websiteTitle(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path === '' ? 'LSTS official website' : 'LSTS · '.str_replace(['-', '/'], [' ', ' · '], $path);
    }

    /** @param Collection<int, SchoolKnowledgeChunk> $chunks */
    private function rankKeywordChunks(Collection $chunks, string $query, int $topK): Collection
    {
        $stopWords = ['của', 'và', 'là', 'có', 'cho', 'theo', 'với', 'một', 'những', 'để', 'trong', 'từ', 'không', 'được', 'cần', 'này', 'đó', 'các', 'vào', 'trên', 'the', 'of', 'and', 'is', 'to', 'in', 'for', 'with', 'on', 'at'];
        $tokens = (array) preg_split('/[\s,.;:!?\/|()\[\]{}]+/u', mb_strtolower($query));
        $tokens = array_values(array_diff(array_filter($tokens), $stopWords));

        return $chunks->map(function (SchoolKnowledgeChunk $chunk) use ($tokens): array {
            $lower = mb_strtolower($chunk->content);
            $score = array_sum(array_map(fn (string $token) => mb_substr_count($lower, $token), $tokens));

            return ['chunk' => $chunk, 'score' => $score];
        })->sortByDesc('score')->take($topK);
    }
}
