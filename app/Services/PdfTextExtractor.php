<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\PhpExecutableFinder;
use Throwable;

class PdfTextExtractor
{
    public function extract(string $binary): string
    {
        $request = request();
        $key = 'ai_plus.pdf_text.'.hash('sha256', $binary);
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $text = app(ChatPerformance::class)->measure('pdf_read', function () use ($binary): string {
            $php = (new PhpExecutableFinder)->find(false);
            if ($php === false) {
                throw ValidationException::withMessages(['documents' => 'PDF text extraction is unavailable on this server.']);
            }

            $script = <<<'PHP'
require $argv[1];
try {
    echo (new \Smalot\PdfParser\Parser)->parseContent(stream_get_contents(STDIN))->getText();
} catch (\Throwable) {
    exit(1);
}
PHP;

            try {
                $result = Process::input($binary)->timeout(20)->run([
                    $php, '-d', 'memory_limit=256M', '-d', 'display_errors=stderr',
                    '-d', 'log_errors=0', '-r', $script, base_path('vendor/autoload.php'),
                ]);
            } catch (ProcessTimedOutException) {
                Log::warning('PDF text extraction timed out', ['timeout_seconds' => 20]);
                throw ValidationException::withMessages(['documents' => 'The PDF took too long to read. Split it into smaller files and try again.']);
            } catch (Throwable $exception) {
                Log::warning('PDF text extraction unavailable', ['exception' => $exception::class]);
                throw ValidationException::withMessages(['documents' => 'PDF text extraction is unavailable on this server.']);
            }

            if (! $result->successful()) {
                Log::warning('PDF text extraction failed', ['exit_code' => $result->exitCode()]);
                if (str_contains($result->errorOutput(), 'Allowed memory size')) {
                    throw ValidationException::withMessages(['documents' => 'The PDF requires too much memory to read. Split it into smaller files and try again.']);
                }

                return '';
            }

            return trim($result->output());
        });

        $request->attributes->set($key, $text);

        return $text;
    }
}
