<?php

namespace App\Services;

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
    /** Cap tổng số ký tự knowledge nạp vào system prompt (≈ 10k tokens). */
    public const MAX_CONTEXT_CHARS = 40000;

    /** Cap ký tự nội dung mỗi file (tránh 1 file quá lớn nuốt context). */
    public const MAX_FILE_CHARS = 20000;

    /** Các extension được phép upload. */
    public const ALLOWED_EXTENSIONS = ['txt', 'csv', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];

    /** Dung lượng tối đa mỗi file (5MB). */
    public const MAX_FILE_SIZE_KB = 5120;

    public function __construct(private readonly RegexPiiFilter $piiFilter) {}

    /**
     * Lưu các file upload vào disk knowledge, trả về danh sách path + tên gốc.
     *
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
     * Xóa thư mục knowledge của một agent (khi xóa agent).
     */
    public function deleteAgentKnowledge(int $userId, int $agentId): void
    {
        Storage::disk('knowledge')->deleteDirectory($userId.'/'.$agentId);
    }

    /**
     * Ghép nội dung các file knowledge thành chuỗi context (đã filter PII, có nhãn nguồn).
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

        foreach ($files as $file) {
            $path = $file['path'];
            $originalName = $file['original_name'];

            if (! $path) {
                continue;
            }

            if ($total >= self::MAX_CONTEXT_CHARS) {
                Log::warning('Knowledge context cap reached; truncating remaining files', [
                    'user_id' => $userId,
                    'agent_id' => $agentId,
                ]);

                break;
            }

            $text = $this->extractText($path, $userId, $agentId);

            if (! $text) {
                continue;
            }

            $text = Str::limit($text, self::MAX_FILE_CHARS);

            // Filter PII trước khi đưa vào context (knowledge có thể chứa HS/PH).
            $text = $this->piiFilter->filter($text)['filtered'];

            // Cắt thêm theo cap tổng.
            $remaining = self::MAX_CONTEXT_CHARS - $total;
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
     * Đọc text thô từ file (không cần filter PII — self::buildContext đã filter).
     */
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
            // Để PhpWord tự detect format (docx = OOXML, doc = MsDoc).
            // Không cưỡng ép 'MsDoc' — nếu không .docx (OOXML) sẽ bị parse sai thành OLE.
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

    /**
     * Đưa file về temp (PhpWord/PhpSpreadsheet cần đường dẫn file thật trên disk).
     */
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
     * Duyệt elements (có thể chứa TextRun/lồng nhau) và append text nếu là string.
     * Tránh lỗi "Object of class TextRun could not be converted to string".
     *
     * @param  array<int, object>  $elements
     */
    private function appendElementText(string &$text, array $elements): void
    {
        foreach ($elements as $element) {
            // TextRun / container: duyệt con.
            if ($element instanceof TextRun) {
                $this->appendElementText($text, $element->getElements());

                continue;
            }

            // Element có lấy text (Text, ListItem, Title, ...) — chỉ append nếu string.
            if (method_exists($element, 'getText')) {
                try {
                    $value = $element->getText();
                    if (is_string($value) && $value !== '') {
                        $text .= $value."\n";
                    }
                } catch (Throwable) {
                    // Bỏ qua element không lấy được text (vd Table, Image...).
                }
            }

            // Table: duyệt row/cell.
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
     * Rule validation cho request (dùng trong FormRequest).
     *
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
