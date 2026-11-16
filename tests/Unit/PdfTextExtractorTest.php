<?php

use App\Services\KnowledgeService;
use App\Services\PdfTextExtractor;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeout;
use Symfony\Component\Process\Process as SymfonyProcess;

it('reads real PDF text through the bounded parser', function () {
    $pdf = new Dompdf;
    $pdf->loadHtml('<p>Workshop sample instructions.</p>');
    $pdf->render();

    expect(app(PdfTextExtractor::class)->extract($pdf->output()))->toContain('Workshop sample instructions.');
});

it('reuses PDF extraction only within the same request', function () {
    Process::fake(['*' => Process::result('Sample document')]);
    $binary = '%PDF-1.4 sample';
    expect(app(KnowledgeService::class)->extractTextFromBinary($binary, 'pdf'))->toBe('Sample document');
    expect(app(KnowledgeService::class)->extractTextFromBinary($binary, 'pdf'))->toBe('Sample document');
    Process::assertRanTimes(fn (PendingProcess $process) => $process->timeout === 20 && $process->input === $binary, 1);

    app()->instance('request', Request::create('/another-request'));
    expect(app(PdfTextExtractor::class)->extract($binary))->toBe('Sample document');
    Process::assertRanTimes(fn (PendingProcess $process) => $process->input === $binary, 2);
});

it('aborts a PDF timeout without logging document text or command input', function () {
    $process = new SymfonyProcess(['php', '-r', 'sample'], timeout: 20);
    $exception = new ProcessTimedOutException(new SymfonyTimeout($process, SymfonyTimeout::TYPE_GENERAL), Process::result());
    Process::fake(fn () => throw $exception);
    Log::spy();

    expect(fn () => app(KnowledgeService::class)->extractTextFromBinary('%PDF-SECRET_SAMPLE', 'pdf'))
        ->toThrow(ValidationException::class);
    Log::shouldHaveReceived('warning')->once()->with('PDF text extraction timed out', ['timeout_seconds' => 20]);
});

it('aborts a PDF memory limit instead of accepting partial extraction', function () {
    Process::fake(['*' => Process::result('partial text', 'Allowed memory size of 268435456 bytes exhausted', 255)]);
    expect(fn () => app(PdfTextExtractor::class)->extract('%PDF-memory-sample'))->toThrow(ValidationException::class);
});

it('keeps empty scan text eligible for the existing scan fallback', function () {
    Process::fake(['*' => Process::result('')]);
    expect(app(PdfTextExtractor::class)->extract('%PDF-scan-sample'))->toBe('');
});

it('reports an unavailable subprocess without leaking command details', function () {
    Process::fake(fn () => throw new RuntimeException('PRIVATE_COMMAND_DETAIL'));
    Log::spy();
    expect(fn () => app(PdfTextExtractor::class)->extract('%PDF-sample'))->toThrow(ValidationException::class);
    Log::shouldHaveReceived('warning')->once()->with('PDF text extraction unavailable', ['exception' => RuntimeException::class]);
});

it('discards partial parser output when the child exits unsuccessfully', function () {
    Process::fake(['*' => Process::result('PARTIAL_OUTPUT', 'PRIVATE_PARSER_DETAIL', 1)]);
    expect(app(PdfTextExtractor::class)->extract('%PDF-broken'))->toBe('');
});

it('propagates PDF timeouts from Agent Knowledge instead of silently losing the source', function () {
    Storage::fake('knowledge');
    Storage::disk('knowledge')->put('sample.pdf', '%PDF-1.4 sample');
    $process = new SymfonyProcess(['php'], timeout: 20);
    $exception = new ProcessTimedOutException(new SymfonyTimeout($process, SymfonyTimeout::TYPE_GENERAL), Process::result());
    Process::fake(fn () => throw $exception);
    expect(fn () => app(KnowledgeService::class)->extractText('sample.pdf', 1, 1))->toThrow(ValidationException::class);
});
