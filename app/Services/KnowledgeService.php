<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\KnowledgeChunk;
use App\Services\Guardrail\RegexPiiFilter;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class KnowledgeService
{
    // Giữ nguyên ảnh + csv (đã thêm trước đó) — KHÔNG bỏ.
    public const ALLOWED_EXTENSIONS = ['txt', 'csv', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

    public const MAX_FILE_SIZE_KB = 5120;

    public function __construct(
        private readonly RegexPiiFilter $piiFilter,
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

    public function deleteAgentKnowledge(int $userId, int $agentId): void
    {
        Storage::disk('knowledge')->deleteDirectory($userId.'/'.$agentId);
        KnowledgeChunk::where('agent_id', $agentId)->delete();
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

        // Project OpenAI không có embedding model → bỏ bước embed, chỉ lưu chunk text.
        // Retrieval dùng keyword/tf-idf (không cần vector).
        foreach ($chunkTexts as $i => $chunkText) {
            KnowledgeChunk::create([
                'agent_id' => $agent->id,
                'source_file' => $chunkMeta[$i]['source_file'],
                'chunk_index' => $chunkMeta[$i]['chunk_index'],
                'content' => $chunkText,
                'embedding' => [],
            ]);
        }

        Log::info('Knowledge indexed for agent (keyword RAG)', [
            'agent_id' => $agent->id,
            'chunk_count' => count($chunkTexts),
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

        // Keyword RAG: token hóa câu hỏi, tính điểm chunk theo số từ khóa trùng khớp.
        // Đơn giản, chạy không cần embedding model. Đủ để demo "lấy đoạn liên quan".
        $stopWords = ['của', 'và', 'là', 'có', 'cho', 'theo', 'với', 'một', 'những', 'để', 'trong', 'từ', 'không', 'được', 'cần', 'này', 'đó', 'các', 'vào', 'trên', 'bởi', 'đã', 'sẽ', 'tôi', 'bạn', 'anh', 'chị', 'em', 'the', 'of', 'and', 'is', 'to', 'in', 'for', 'with', 'on', 'at', 'not', 'have', 'be'];
        $tokens = (array) preg_split('/[\s,.;:!?\/|()\[\]{}]+/u', mb_strtolower($query));
        $tokens = array_filter($tokens);
        $tokens = array_diff($tokens, $stopWords);

        if ($tokens === []) {
            // Không có từ khóa — fallback lấy các chunk đầu.
            $scored = $chunks->take($topK);
        } else {
            $scored = $chunks->map(function (KnowledgeChunk $chunk) use ($tokens) {
                $lower = mb_strtolower($chunk->content);

                $score = 0;
                foreach ($tokens as $token) {
                    $score += mb_substr_count($lower, mb_strtolower($token));
                }

                return ['chunk' => $chunk, 'score' => $score];
            })->sortByDesc('score')->take($topK);
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
            $spreadsheet = SpreadsheetIOFactory::load($tmp);
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
        } finally {
            @unlink($tmp);
        }
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
            'knowledge' => 'nullable|array',
            'knowledge.*' => [
                'file',
                'max:'.self::MAX_FILE_SIZE_KB,
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            ],
        ];
    }
}
