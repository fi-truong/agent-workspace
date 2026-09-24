<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AiArtifact;
use App\Models\Conversation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as WordHtml;

class ArtifactService
{
    public function generate(User $user, ?Conversation $conversation, string $type, string $content, ?string $filename = null): AiArtifact
    {
        $base = 'ai-plus-document';
        [, $mime, $binary] = match ($type) {
            'excel' => $this->excel($base, $content),
            'word' => $this->word($base, $content),
            'pdf' => $this->pdf($base, $content),
            'html' => $this->html($base, $content),
            default => [$base.'.txt', 'text/plain', $content],
        };
        $name = $this->filename($type, $content, $filename);
        $path = $user->id.'/'.Str::uuid().'/'.$name;
        Storage::disk('ai-artifacts')->put($path, $binary);
        $artifact = AiArtifact::create(['user_id' => $user->id, 'conversation_id' => $conversation?->id, 'name' => $name, 'mime_type' => $mime, 'path' => $path, 'size' => strlen($binary)]);
        AdminAuditLog::record('ai_artifact.created', $artifact, ['name' => $name, 'mime_type' => $mime]);

        return $artifact;
    }

    private function filename(string $type, string $content, ?string $requestedName): string
    {
        $extension = match ($type) {
            'word' => 'docx',
            'excel' => 'xlsx',
            'pdf' => 'pdf',
            'html' => 'html',
            default => 'txt',
        };
        $requestedName = trim((string) $requestedName);
        $base = pathinfo($requestedName, PATHINFO_FILENAME);

        if ($base === '') {
            preg_match('/^#{1,6}\s+(.+)$/m', $content, $heading);
            $firstLine = collect(preg_split('/\R/', $content) ?: [])
                ->map(fn (string $line) => trim($line, "# -*\t"))
                ->first(fn (string $line) => $line !== '');
            $base = $heading[1] ?? $firstLine ?? 'ai-plus-document';
            $base = Str::slug($base);
            $base = $base !== '' ? Str::limit($base, 64, '') : 'ai-plus-document';

            return $base.'-'.now()->format('Y-m-d').'.'.$extension;
        }

        $base = Str::slug($base);
        $base = $base !== '' ? Str::limit($base, 80, '') : 'ai-plus-document';

        return $base.'.'.$extension;
    }

    private function excel(string $base, string $content): array
    {
        $s = new Spreadsheet;
        $sheet = $s->getActiveSheet();
        $sheet->setTitle('AI+ Export');
        $lines = preg_split('/\R/', $content) ?: [];
        $row = 1;
        $widestColumn = 1;

        for ($index = 0; $index < count($lines); $index++) {
            $line = trim($lines[$index]);
            if ($line === '') {
                $row++;
                continue;
            }

            if ($this->isMarkdownTableStart($lines, $index)) {
                $headers = $this->markdownTableCells($lines[$index]);
                $widestColumn = max($widestColumn, count($headers));
                $this->writeExcelRow($sheet, $row++, $headers, true);
                $index++; // separator row (---|---)

                while (isset($lines[$index + 1]) && str_contains($lines[$index + 1], '|')) {
                    $cells = $this->markdownTableCells($lines[++$index]);
                    if ($cells === []) {
                        break;
                    }
                    $widestColumn = max($widestColumn, count($cells));
                    $this->writeExcelRow($sheet, $row++, $cells);
                }
                $row++;
                continue;
            }

            $heading = preg_match('/^#{1,6}\s+(.+)$/u', $line, $matches) === 1;
            $text = $this->markdownPlainText($heading ? $matches[1] : $line);
            $sheet->setCellValue('A'.$row, $text);
            $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
            if ($heading) {
                $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF4F1');
            }
            $row++;
        }

        for ($column = 1; $column <= $widestColumn; $column++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($s))->save($tmp);
        $b = file_get_contents($tmp);
        @unlink($tmp);

        return [$base.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $b];
    }

    private function word(string $base, string $content): array
    {
        $w = new PhpWord;
        $w->setDefaultFontName('Aptos');
        $w->setDefaultFontSize(11);
        $section = $w->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1100,
            'marginRight' => 1100,
        ]);

        // AI replies are Markdown in the chat UI. Convert the same Markdown to
        // safe HTML first so Word preserves headings, paragraphs, lists, bold
        // text and tables instead of showing raw #, ** and | characters.
        WordHtml::addHtml($section, $this->markdownHtml($content), false, false);

        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        WordWriter::createWriter($w, 'Word2007')->save($tmp);
        $b = file_get_contents($tmp);
        @unlink($tmp);

        return [$base.'.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $b];
    }

    private function pdf(string $base, string $content): array
    {
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;color:#1f2937;font-size:11px;line-height:1.55}'
            .'h1{font-size:22px;color:#1f5d50;margin:0 0 14px}h2{font-size:17px;color:#1f5d50;margin-top:20px}'
            .'h3{font-size:14px;margin-top:16px}table{width:100%;border-collapse:collapse;margin:12px 0}'
            .'th{background:#1f5d50;color:#fff;font-weight:bold}th,td{border:1px solid #cbd5d1;padding:7px;text-align:left}'
            .'blockquote{border-left:3px solid #dca52e;padding-left:10px;color:#475569}code{font-family:monospace;background:#f1f5f9;padding:2px 4px}'
            .'</style></head><body>'.$this->markdownHtml($content).'</body></html>';

        return [$base.'.pdf', 'application/pdf', Pdf::loadHTML($html)->output()];
    }

    private function html(string $base, string $content): array
    {
        if (preg_match('/```html\s*\n(.*?)```/is', $content, $match)) {
            return [$base.'.html', 'text/html; charset=UTF-8', trim($match[1])];
        }

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>AI+ Export</title><style>'
            .'body{margin:0;background:#f4f8f6;color:#21332f;font:16px/1.6 Inter,Arial,sans-serif}.document{max-width:900px;margin:48px auto;padding:48px;background:#fff;border:1px solid #d7e4df;border-radius:16px;box-shadow:0 12px 35px rgba(25,65,54,.08)}'
            .'h1,h2,h3{color:#1f5d50;line-height:1.25}h1{font-size:2rem}h2{margin-top:2rem}table{width:100%;border-collapse:collapse;margin:1rem 0}th{background:#1f5d50;color:#fff}th,td{padding:10px;border:1px solid #d7e4df;text-align:left}blockquote{margin:1rem 0;border-left:4px solid #dca52e;padding:.25rem 1rem;background:#fffaf0}code{background:#eef5f2;padding:.1rem .3rem;border-radius:4px}pre{overflow:auto;padding:1rem;background:#182b27;color:#edf7f3;border-radius:8px}'
            .'</style></head><body><main class="document">'.$this->markdownHtml($content).'</main></body></html>';

        return [$base.'.html', 'text/html; charset=UTF-8', $html];
    }

    private function markdownHtml(string $content): string
    {
        $markdown = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $html = $markdown->convert($content)->getContent();

        // Generated documents never need remote images; excluding them avoids
        // network access while the PDF/Word renderer processes AI output.
        return preg_replace('#<img\\b[^>]*>#i', '', $html) ?? $html;
    }

    /** @param array<int, string> $lines */
    private function isMarkdownTableStart(array $lines, int $index): bool
    {
        return isset($lines[$index + 1])
            && str_contains($lines[$index], '|')
            && preg_match('/^\\s*\\|?\\s*:?-{3,}:?\\s*(?:\\|\\s*:?-{3,}:?\\s*)+\\|?\\s*$/', $lines[$index + 1]) === 1;
    }

    /** @return array<int, string> */
    private function markdownTableCells(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');

        return $line === '' ? [] : array_map(fn (string $cell): string => $this->markdownPlainText(trim($cell)), explode('|', $line));
    }

    /** @param array<int, string> $cells */
    private function writeExcelRow($sheet, int $row, array $cells, bool $header = false): void
    {
        foreach ($cells as $index => $cell) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $coordinate = $letter.$row;
            // AI-generated strings beginning with =, +, - or @ must stay
            // text when the workbook is opened, never executable formulas.
            $sheet->setCellValueExplicit($coordinate, $cell, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyle($coordinate)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle($coordinate)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5E2DD');
            if ($header) {
                $sheet->getStyle($coordinate)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($coordinate)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F5D50');
            }
        }
    }

    private function markdownPlainText(string $text): string
    {
        $text = preg_replace('/!?(?:\\[([^\\]]*)\\])\\([^\\)]*\\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/(\\*\\*|__|`|~~|\\*|_)/u', '', $text) ?? $text;
        $text = preg_replace('/^(?:[-*+] |\\d+\\. )/u', '', $text) ?? $text;

        return trim(strip_tags($text));
    }
}
