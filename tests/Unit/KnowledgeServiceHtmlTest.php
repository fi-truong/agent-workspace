<?php

use App\Services\KnowledgeService;

it('extracts safe readable text from HTML without scripts or embedded content', function () {
    $html = '<h1>School guide</h1><p>Useful <strong>content</strong>.</p><script>window.stolen = true;</script><iframe src="https://example.com"></iframe>';

    $text = app(KnowledgeService::class)->extractTextFromBinary($html, 'html');

    expect($text)->toContain('School guide')
        ->toContain('Useful content.')
        ->not->toContain('window.stolen')
        ->not->toContain('example.com');
});

it('keeps HTML source as safe text for a code-editing task', function () {
    $source = '<main><h1>Original</h1></main><script>console.log("not executed")</script>';

    expect(app(KnowledgeService::class)->htmlSourceForCode($source))
        ->toContain('<main>')
        ->toContain('<script>')
        ->toContain('not executed');
});
