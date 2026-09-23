<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AiArtifact;
use App\Models\Conversation;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordWriter;
use PhpOffice\PhpWord\PhpWord;

class ArtifactService
{
    public function generate(User $user, ?Conversation $conversation, string $type, string $content): AiArtifact
    {
        $base = 'ai-'.now()->format('Ymd-His');
        [$name, $mime, $binary] = match ($type) {
            'excel' => $this->excel($base, $content),
            'word' => $this->word($base, $content),
            'pdf' => $this->pdf($base, $content),
            'html' => $this->html($base, $content),
            default => [$base.'.txt', 'text/plain', $content],
        };
        $path = $user->id.'/'.Str::uuid().'/'.$name;
        Storage::disk('ai-artifacts')->put($path, $binary);
        $artifact = AiArtifact::create(['user_id' => $user->id, 'conversation_id' => $conversation?->id, 'name' => $name, 'mime_type' => $mime, 'path' => $path, 'size' => strlen($binary)]);
        AdminAuditLog::record('ai_artifact.created', $artifact, ['name' => $name, 'mime_type' => $mime]);

        return $artifact;
    }

    private function excel(string $base, string $content): array
    {
        $s = new Spreadsheet;
        $sheet = $s->getActiveSheet();
        foreach (preg_split('/\R/', $content) ?: [] as $i => $line) {
            $sheet->setCellValue('A'.($i + 1), $line);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($s))->save($tmp);
        $b = file_get_contents($tmp);
        @unlink($tmp);

        return [$base.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $b];
    }

    private function word(string $base, string $content): array
    {
        $w = new PhpWord;
        $w->addSection()->addText(htmlspecialchars($content));
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        WordWriter::createWriter($w, 'Word2007')->save($tmp);
        $b = file_get_contents($tmp);
        @unlink($tmp);

        return [$base.'.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $b];
    }

    private function pdf(string $base, string $content): array
    {
        return [$base.'.pdf', 'application/pdf', Pdf::loadHTML(nl2br(e($content)))->output()];
    }

    private function html(string $base, string $content): array
    {
        if (preg_match('/```html\s*\n(.*?)```/is', $content, $match)) {
            $content = trim($match[1]);
        }

        return [$base.'.html', 'text/html; charset=UTF-8', $content];
    }
}
