<?php

use App\Services\KnowledgeService;
use App\Services\PdfTextExtractor;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('reads real PDF text without an AI+ extraction timeout', function () {
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
    Process::assertRanTimes(fn (PendingProcess $process) => $process->timeout === 0 && $process->input === $binary, 1);

    app()->instance('request', Request::create('/another-request'));
    expect(app(PdfTextExtractor::class)->extract($binary))->toBe('Sample document');
    Process::assertRanTimes(fn (PendingProcess $process) => $process->input === $binary, 2);
});

it('does not impose an extraction timeout on PDF processing', function () {
    Process::fake(['*' => Process::result('Complete PDF text')]);

    expect(app(KnowledgeService::class)->extractTextFromBinary('%PDF-SAMPLE', 'pdf'))->toBe('Complete PDF text');
    Process::assertRanTimes(fn (PendingProcess $process) => $process->timeout === 0, 1);
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
    Process::fake(['*' => Process::result('Complete Knowledge PDF text')]);

    expect(app(KnowledgeService::class)->extractText('sample.pdf', 1, 1))->toBe('Complete Knowledge PDF text');
    Process::assertRanTimes(fn (PendingProcess $process) => $process->timeout === 0, 1);
});
