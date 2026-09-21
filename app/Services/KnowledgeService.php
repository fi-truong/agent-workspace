<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\KnowledgeChunk;
use App\Services\Guardrail\RegexPiiFilter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\Process\Process;
use Throwable;

class KnowledgeService
{
    // Giữ nguyên ảnh + csv (đã thêm trước đó) — KHÔNG bỏ.
    public const ALLOWED_EXTENSIONS = ['txt', 'csv', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * File types accepted as direct attachments in a chat message.
     * Images use the separate multimodal chat path and are not included here.
     */
    public const CHAT_DOCUMENT_EXTENSIONS = ['txt', 'csv', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];

    public const MAX_FILE_SIZE_KB = 5120;

    public const MAX_AGENT_FILES = 10;

    public const MAX_AGENT_TOTAL_SIZE_KB = 25_600;

    public function __construct(
        private readonly RegexPiiFilter $piiFilter,
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path: string, original_name: string}>
     */
    public function saveFiles(array $files, int $userId, int $agentId): array
    {
        $saved = [];

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());

            if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $relativeDir = $userId.'/'.$agentId;
            $storedPath = $file->store($relativeDir, 'knowledge');

            if ($storedPath === false) {
                Log::warning('Failed to store knowledge file', [
                    'user_id' => $userId,
                    'agent_id' => $agentId,
                    'name' => $file->getClientOriginalName(),
                ]);

                continue;
            }

            $saved[] = [
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        return $saved;
    }

    /**
     * @param  array<int, UploadedFile>  $newFiles
     * @param  array<int, array{path: string, original_name: string}>  $existingFiles
     */
    public function ensureAgentKnowledgeLimits(array $newFiles, array $existingFiles = []): void
    {
        if (count($existingFiles) + count($newFiles) > self::MAX_AGENT_FILES) {
            throw ValidationException::withMessages([
                'knowledge' => 'Mỗi Agent chỉ được tối đa '.self::MAX_AGENT_FILES.' file Knowledge.',
            ]);
        }

        $existingBytes = collect($existingFiles)->sum(function (array $file): int {
            $path = $file['path'] ?? '';

            return $path !== '' && Storage::disk('knowledge')->exists($path)
                ? (int) Storage::disk('knowledge')->size($path)
                : 0;
        });
        $newBytes = collect($newFiles)->sum(fn (UploadedFile $file): int => (int) $file->getSize());

        if ($existingBytes + $newBytes > self::MAX_AGENT_TOTAL_SIZE_KB * 1024) {
            throw ValidationException::withMessages([
                'knowledge' => 'Tổng dung lượng Knowledge của mỗi Agent chỉ được tối đa 25 MB.',
            ]);
        }
    }

    public function deleteAgentKnowledge(int $userId, int $agentId): void
    {
        Storage::disk('knowledge')->deleteDirectory($userId.'/'.$agentId);
        KnowledgeChunk::where('agent_id', $agentId)->delete();
    }

    /** Copy explicitly shared Knowledge files into a recipient-owned Agent and index its RAG context. */
    public function copySharedKnowledge(Agent $source, Agent $recipient): void
    {
        if ($source->sharing_access !== 'copy' || $source->knowledge_files === []) {
            return;
        }

        $disk = Storage::disk('knowledge');
        $copied = [];

        foreach ($source->knowledge_files as $file) {
            $sourcePath = $file['path'] ?? '';
            if ($sourcePath === '' || ! $disk->exists($sourcePath)) {
                continue;
            }

            $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $destinationPath = $recipient->user_id.'/'.$recipient->id.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');
            if (! $disk->copy($sourcePath, $destinationPath)) {
                Log::warning('Failed to copy shared knowledge file', [
                    'source_agent_id' => $source->id,
                    'recipient_agent_id' => $recipient->id,
                    'path' => $sourcePath,
                ]);

                continue;
            }

            $copied[] = [
                'path' => $destinationPath,
                'original_name' => $file['original_name'] ?? basename($sourcePath),
            ];
        }

        if ($copied === []) {
            return;
        }

        $recipient->update(['knowledge' => json_encode($copied)]);
        $this->indexAgent($recipient->fresh());
    }

    /**
     * RAG — index toàn bộ Knowledge của agent: xoá chunk cũ, đọc lại file, cắt nhỏ + embed + lưu.
     * Gọi lại mỗi khi file Knowledge thay đổi.
     */
    public function indexAgent(Agent $agent): void
    {
        KnowledgeChunk::where('agent_id', $agent->id)->delete();

        $files = $agent->knowledge_files;

        if ($files === []) {
            return;
        }

        $chunkTexts = [];
        $chunkMeta = [];

        foreach ($files as $file) {
            $text = $this->extractText($file['path'], $agent->user_id, $agent->id);

            if (! $text) {
                continue;
            }

            // Filter PII trước khi chunk/embed.
            $text = $this->piiFilter->filter($text)['filtered'];

            $chunks = $this->chunkText(
                $text,
                (int) config('openai.rag_chunk_chars'),
                (int) config('openai.rag_chunk_overlap'),
            );

            foreach ($chunks as $i => $chunkText) {
                $chunkTexts[] = $chunkText;
                $chunkMeta[] = [
                    'source_file' => $file['original_name'],
                    'chunk_index' => $i,
                ];
            }
        }

        if ($chunkTexts === []) {
            return;
        }

        // Một request batch cho toàn bộ đoạn giúp lập chỉ mục nhanh và tiết kiệm hơn.
        // Nếu API embeddings chưa được cấp quyền hoặc lỗi tạm thời, vẫn lưu text và
        // retrieveContext() sẽ tự động dùng keyword RAG.
        $embeddings = $this->embeddingService->embedBatch($chunkTexts);
        $hasEmbeddings = count($embeddings) === count($chunkTexts)
            && collect($embeddings)->every(fn ($embedding) => is_array($embedding) && $embedding !== []);

        foreach ($chunkTexts as $i => $chunkText) {
            KnowledgeChunk::create([
                'agent_id' => $agent->id,
                'source_file' => $chunkMeta[$i]['source_file'],
                'chunk_index' => $chunkMeta[$i]['chunk_index'],
                'content' => $chunkText,
                'embedding' => $hasEmbeddings ? $embeddings[$i] : [],
            ]);
        }

        Log::info('Knowledge indexed for agent', [
            'agent_id' => $agent->id,
            'chunk_count' => count($chunkTexts),
            'strategy' => $hasEmbeddings ? 'semantic' : 'keyword',
        ]);
    }

    /**
     * RAG — lấy các đoạn liên quan nhất đến câu hỏi hiện tại.
     */
    public function retrieveContext(Agent $agent, string $query, ?int $topK = null): string
    {
        $topK ??= (int) config('openai.rag_top_k', 4);

        $chunks = KnowledgeChunk::where('agent_id', $agent->id)->get();

        if ($chunks->isEmpty()) {
            return '';
        }

        $queryEmbedding = $this->embeddingService->embed($query);
        $semanticChunks = $chunks->filter(fn (KnowledgeChunk $chunk) => $chunk->embedding !== []);

        if ($queryEmbedding !== [] && $semanticChunks->isNotEmpty()) {
            $scored = $semanticChunks->map(fn (KnowledgeChunk $chunk) => [
                'chunk' => $chunk,
                'score' => $this->embeddingService->cosineSimilarity($queryEmbedding, $chunk->embedding),
            ])->sortByDesc('score')->take($topK);
        } else {
            $scored = $this->rankKeywordChunks($chunks->all(), $query, $topK);
        }

        if ($scored->isEmpty()) {
            return '';
        }

        $parts = $scored->map(function ($item) {
            $chunk = is_array($item) ? $item['chunk'] : $item;

            return "[Trích từ file: {$chunk->source_file}]\n{$chunk->content}\n[/Trích]";
        })->values()->all();

        return "=== KNOWLEDGE (đoạn liên quan nhất đến câu hỏi, RAG) ===\n\n".implode("\n\n", $parts);
    }

    /**
     * RAG không lưu trạng thái cho tài liệu đính kèm trực tiếp trong một lượt chat.
     * Chỉ các đoạn phù hợp với câu hỏi được đưa vào prompt, còn file gốc không lưu lại.
     */
    public function retrieveInlineContext(string $text, string $query, string $sourceFile, ?int $topK = null): string
    {
        $topK ??= (int) config('openai.rag_top_k', 4);
        $chunks = $this->chunkText(
            $text,
            (int) config('openai.rag_chunk_chars'),
            (int) config('openai.rag_chunk_overlap'),
        );

        if ($chunks === []) {
            return '';
        }

        // Trực tiếp embed câu hỏi + các đoạn trong một request; không lưu tệp của chat.
        $vectors = $this->embeddingService->embedBatch([$query, ...$chunks]);
        $hasEmbeddings = count($vectors) === count($chunks) + 1
            && $vectors[0] !== []
            && collect(array_slice($vectors, 1))->every(fn ($embedding) => $embedding !== []);

        if ($hasEmbeddings) {
            $queryVector = $vectors[0];
            $ranked = array_map(fn (string $chunk, int $index): array => [
                'content' => $chunk,
                'score' => $this->embeddingService->cosineSimilarity($queryVector, $vectors[$index + 1]),
            ], $chunks, array_keys($chunks));
            usort($ranked, fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        } else {
            $ranked = $this->rankKeywordTextChunks($chunks, $query, $topK);
        }

        $parts = array_slice($ranked, 0, $topK);

        return implode("\n\n", array_map(
            fn (array $chunk): string => "[Trích từ file: {$sourceFile}]\n{$chunk['content']}\n[/Trích]",
            $parts,
        ));
    }

    /**
     * @return array<int, string>
     */
    private function queryTokens(string $query): array
    {
        $stopWords = ['của', 'và', 'là', 'có', 'cho', 'theo', 'với', 'một', 'những', 'để', 'trong', 'từ', 'không', 'được', 'cần', 'này', 'đó', 'các', 'vào', 'trên', 'bởi', 'đã', 'sẽ', 'tôi', 'bạn', 'anh', 'chị', 'em', 'the', 'of', 'and', 'is', 'to', 'in', 'for', 'with', 'on', 'at', 'not', 'have', 'be'];
        $tokens = (array) preg_split('/[\s,.;:!?\/|()\[\]{}]+/u', mb_strtolower($query));

        return array_values(array_diff(array_filter($tokens), $stopWords));
    }

    /**
     * @param  array<int, KnowledgeChunk>  $chunks
     * @return Collection<int, array{chunk: KnowledgeChunk, score: int}>
     */
    private function rankKeywordChunks(array $chunks, string $query, int $topK): Collection
    {
        $tokens = $this->queryTokens($query);

        if ($tokens === []) {
            return collect($chunks)->take($topK)->map(fn (KnowledgeChunk $chunk) => ['chunk' => $chunk, 'score' => 0]);
        }

        return collect($chunks)->map(function (KnowledgeChunk $chunk) use ($tokens) {
            $lower = mb_strtolower($chunk->content);
            $score = array_sum(array_map(fn (string $token) => mb_substr_count($lower, $token), $tokens));

            return ['chunk' => $chunk, 'score' => $score];
        })->sortByDesc('score')->take($topK);
    }

    /**
     * @param  array<int, string>  $chunks
     * @return array<int, array{content: string, score: int}>
     */
    private function rankKeywordTextChunks(array $chunks, string $query, int $topK): array
    {
        $tokens = $this->queryTokens($query);
        $ranked = array_map(function (string $chunk) use ($tokens): array {
            $lower = mb_strtolower($chunk);
            $score = array_sum(array_map(fn (string $token) => mb_substr_count($lower, $token), $tokens));

            return ['content' => $chunk, 'score' => $score];
        }, $chunks);
        usort($ranked, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_slice($ranked, 0, $topK);
    }

    /**
     * FALLBACK — đọc nguyên văn toàn bộ file (giữ hành vi cũ). Dùng khi RAG chưa index / embed lỗi.
     *
     * @param  array<int, array{path: string, original_name: string}>|null  $files
     */
    public function buildContext(?array $files, int $userId, int $agentId): string
    {
        if ($files === null || $files === []) {
            return '';
        }

        $parts = [];
        $total = 0;
        $maxContext = 40000;

        foreach ($files as $file) {
            $path = $file['path'];
            $originalName = $file['original_name'];

            if (! $path) {
                continue;
            }

            if ($total >= $maxContext) {
                break;
            }

            $text = $this->extractText($path, $userId, $agentId);

            if (! $text) {
                continue;
            }

            $text = Str::limit($text, 20000);
            $text = $this->piiFilter->filter($text)['filtered'];

            $remaining = $maxContext - $total;
            if (mb_strlen($text) > $remaining) {
                $text = mb_substr($text, 0, $remaining);
            }

            $total += mb_strlen($text);
            $parts[] = "[Kiến thức từ file: {$originalName}]\n{$text}\n[/Kiến thức từ file]";
        }

        if (count($parts) === 0) {
            return '';
        }

        return "=== KNOWLEDGE (thông tin tham khảo từ các file đã upload) ===\n\n".implode("\n\n", $parts);
    }

    /**
     * Cắt text thành các đoạn ~$chunkChars ký tự, chồng lấn $overlapChars giữa 2 đoạn liền kề,
     * cắt tại khoảng trắng gần nhất để không đứt giữa từ.
     *
     * @return array<int, string>
     */
    public function chunkText(string $text, int $chunkChars, int $overlapChars): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunkChars, $length);

            if ($end < $length) {
                $slice = mb_substr($text, $start, $end - $start);
                $lastSpace = mb_strrpos($slice, ' ');

                if ($lastSpace !== false && $lastSpace > 0) {
                    $end = $start + $lastSpace;
                }
            }

            $chunk = trim(mb_substr($text, $start, $end - $start));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($end >= $length) {
                break;
            }

            $start = max($end - $overlapChars, $start + 1);
        }

        return $chunks;
    }

    public function extractText(string $path, int $userId, int $agentId): string
    {
        $disk = Storage::disk('knowledge');

        if (! $disk->exists($path)) {
            Log::warning('Knowledge file missing on disk', [
                'user_id' => $userId,
                'agent_id' => $agentId,
                'path' => $path,
            ]);

            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            return match ($extension) {
                'txt', 'csv' => $disk->get($path) ?: '',
                'pdf' => $this->readPdf($disk, $path),
                'doc', 'docx' => $this->readWord($disk, $path),
                'xls', 'xlsx' => $this->readExcel($disk, $path),
                // Ảnh lưu được nhưng chưa OCR.
                'png', 'jpg', 'jpeg', 'gif', 'webp' => '',
                default => '',
            };
        } catch (Throwable $e) {
            Log::error('Failed to extract text from knowledge file', [
                'user_id' => $userId,
                'agent_id' => $agentId,
                'path' => $path,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Trich text tu noi dung file tho (base64-decoded) - dung cho tai lieu dinh kem truc tiep
     * trong o chat cua Agent Workspace. Khac voi extractText() o tren: khong doc tu disk
     * "knowledge" (khong gan voi 1 Agent cu the), chi nhan binary content + extension.
     */
    public function extractTextFromBinary(string $binary, string $extension): string
    {
        $extension = strtolower($extension);

        if (! in_array($extension, self::CHAT_DOCUMENT_EXTENSIONS, true)) {
            return '';
        }

        if ($extension === 'txt' || $extension === 'csv') {
            return $binary;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'chat_doc_');

        if ($tmp === false) {
            return '';
        }

        file_put_contents($tmp, $binary);

        try {
            return match ($extension) {
                'pdf' => trim((new PdfParser)->parseContent($binary)->getText()),
                'doc', 'docx' => trim($this->renderWordText(WordIOFactory::load($tmp))),
                'xls', 'xlsx' => $this->renderSpreadsheetText(SpreadsheetIOFactory::load($tmp)),
                // ppt/pptx: cho phep dinh kem nhung chua co lib trich text (thieu PhpPresentation).
                default => '',
            };
        } catch (Throwable $e) {
            Log::error('Failed to extract text from chat document', [
                'extension' => $extension,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return '';
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Render scanned PDF pages as JPEG data URLs for the existing multimodal
     * chat flow. Text PDFs do not need this because their text is extracted
     * directly. Returns an empty array if Poppler is not installed/configured.
     *
     * @return array<int, string>
     */
    public function renderScannedPdfPages(string $binary): array
    {
        $renderer = $this->pdfScanRenderer();
        if ($renderer === null) {
            Log::warning('Scanned PDF cannot be rendered: pdftoppm is unavailable');

            return [];
        }

        $dir = sys_get_temp_dir().'/chat_pdf_'.Str::random(24);
        if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return [];
        }

        $input = $dir.'/source.pdf';
        $prefix = $dir.'/page';

        try {
            if (file_put_contents($input, $binary) === false) {
                return [];
            }

            $process = new Process([
                $renderer, '-jpeg', '-f', '1', '-l', (string) max(1, config('openai.pdf_scan_max_pages', 3)),
                '-scale-to-x', (string) max(320, config('openai.pdf_scan_max_width', 1280)), '-scale-to-y', '-1',
                $input, $prefix,
            ]);
            $process->setTimeout(45);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('Scanned PDF render failed', ['exit_code' => $process->getExitCode()]);

                return [];
            }

            $pages = glob($prefix.'-*.jpg') ?: [];
            natsort($pages);

            return collect($pages)
                ->take(max(1, config('openai.pdf_scan_max_pages', 3)))
                ->map(function (string $page): ?string {
                    $image = file_get_contents($page);

                    return $image !== false && strlen($image) <= 2 * 1024 * 1024
                        ? 'data:image/jpeg;base64,'.base64_encode($image)
                        : null;
                })
                ->filter()
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::warning('Scanned PDF render failed', ['exception' => get_class($e), 'message' => $e->getMessage()]);

            return [];
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function pdfScanRenderer(): ?string
    {
        $configured = config('openai.pdf_scan_renderer_binary');
        $candidates = array_filter([
            is_string($configured) ? $configured : null,
            '/opt/homebrew/bin/pdftoppm',
            '/usr/local/bin/pdftoppm',
            '/usr/bin/pdftoppm',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Ap PII filter (Layer 1, regex) + cat do dai - dung cho text trich tu tai lieu
     * dinh kem trong chat truoc khi dua vao ngu canh gui cho model.
     */
    public function filterAndTruncate(string $text, int $maxChars = 20000): string
    {
        $text = $this->piiFilter->filter($text)['filtered'];

        return Str::limit($text, $maxChars);
    }

    private function readPdf(Filesystem $disk, string $path): string
    {
        $content = $disk->get($path);

        if (! $content) {
            return '';
        }

        $parser = new PdfParser;
        $pdf = $parser->parseContent($content);

        return trim($pdf->getText());
    }

    private function readWord(Filesystem $disk, string $path): string
    {
        $tmp = $this->tempCopy($disk, $path);

        if (! $tmp) {
            return '';
        }

        try {
            $phpWord = WordIOFactory::load($tmp);
            $tmpText = $this->renderWordText($phpWord);

            return trim($tmpText);
        } finally {
            @unlink($tmp);
        }
    }

    private function readExcel(Filesystem $disk, string $path): string
    {
        $tmp = $this->tempCopy($disk, $path);

        if (! $tmp) {
            return '';
        }

        try {
            return $this->renderSpreadsheetText(SpreadsheetIOFactory::load($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  Spreadsheet  $spreadsheet
     */
    private function renderSpreadsheetText($spreadsheet): string
    {
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $text .= "[Sheet: {$sheet->getTitle()}]\n";

            foreach ($sheet->toArray() as $row) {
                $cells = array_filter($row, fn ($cell) => $cell !== null && trim((string) $cell) !== '');
                if ($cells) {
                    $text .= implode(' | ', $cells)."\n";
                }
            }

            $text .= "\n";
        }

        return trim($text);
    }

    private function tempCopy(Filesystem $disk, string $path): ?string
    {
        $content = $disk->get($path);

        if (! $content) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'knowledge_');

        if ($tmp === false) {
            return null;
        }

        file_put_contents($tmp, $content);

        return $tmp;
    }

    private function renderWordText(PhpWord $phpWord): string
    {
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            $this->appendElementText($text, $section->getElements());
        }

        return $text;
    }

    /**
     * @param  array<int, object>  $elements
     */
    private function appendElementText(string &$text, array $elements): void
    {
        foreach ($elements as $element) {
            if ($element instanceof TextRun) {
                $this->appendElementText($text, $element->getElements());

                continue;
            }

            if (method_exists($element, 'getText')) {
                try {
                    $value = $element->getText();
                    if (is_string($value) && $value !== '') {
                        $text .= $value."\n";
                    }
                } catch (Throwable) {
                    // Bỏ qua element không lấy được text.
                }
            }

            if ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $this->appendElementText($text, $cell->getElements());
                    }
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'knowledge' => 'nullable|array|max:'.self::MAX_AGENT_FILES,
            'knowledge.*' => [
                'file',
                'max:'.self::MAX_FILE_SIZE_KB,
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            ],
        ];
    }
}
