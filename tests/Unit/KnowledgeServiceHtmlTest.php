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

it('recovers text from a DOCX with malformed Office Math namespaces', function () {
    $path = tempnam(sys_get_temp_dir(), 'broken-docx-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Open Class Lesson Plan</w:t></w:r></w:p>
    <m:oMath><m:t>sin x</m:t></m:oMath>
  </w:body>
</w:document>
XML);
    $archive->close();

    try {
        $binary = file_get_contents($path);
        $text = app(KnowledgeService::class)->extractTextFromBinary($binary, 'docx');

        expect($text)->toContain('Open Class Lesson Plan')
            ->toContain('sin x');
    } finally {
        @unlink($path);
    }
});

it('decodes literal HTML entities in text extracted from Word', function () {
    $path = tempnam(sys_get_temp_dir(), 'entities-docx-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>Goal &amp;amp; Use Case &amp;quot;overview&amp;quot;</w:t></w:r></w:p></w:body>
</w:document>
XML);
    $archive->close();

    try {
        $text = app(KnowledgeService::class)->extractTextFromBinary(file_get_contents($path), 'docx');

        expect($text)->toContain('Goal & Use Case "overview"')
            ->not->toContain('&amp;');
    } finally {
        @unlink($path);
    }
});

it('reads Word text after punctuation and inside nested containers in order', function () {
    $path = tempnam(sys_get_temp_dir(), 'punctuation-docx-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Goal &amp; Use Case - phần đầu; "trích dẫn" / 100%.</w:t></w:r></w:p>
    <w:p><w:r><w:t>Sau dấu gạch ngang</w:t><w:noBreakHyphen/><w:t>vẫn còn nội dung.</w:t></w:r></w:p>
    <w:p><w:r><w:drawing><w:txbxContent>
      <w:p><w:r><w:t>Nội dung trong khung chữ — không được bỏ sót.</w:t></w:r></w:p>
    </w:txbxContent></w:drawing></w:r></w:p>
    <w:p><w:r><w:t>Phần cuối tài liệu: 4.1 Knowledge &amp; Privacy ✓</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);
    $archive->close();

    try {
        $text = app(KnowledgeService::class)->extractTextFromBinary(file_get_contents($path), 'docx');

        expect($text)->toContain('Goal & Use Case - phần đầu')
            ->toContain('Sau dấu gạch ngang-vẫn còn nội dung.')
            ->toContain('Nội dung trong khung chữ — không được bỏ sót.')
            ->toContain('Phần cuối tài liệu: 4.1 Knowledge & Privacy ✓')
            ->and(mb_strpos($text, 'Goal & Use Case'))->toBeLessThan(mb_strpos($text, 'Phần cuối tài liệu'));
    } finally {
        @unlink($path);
    }
});

it('extracts slide text directly from a PPTX package', function () {
    $path = tempnam(sys_get_temp_dir(), 'slides-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('ppt/slides/slide2.xml', '<p:sld xmlns:p="p" xmlns:a="a"><a:t>Second slide</a:t></p:sld>');
    $archive->addFromString('ppt/slides/slide1.xml', '<p:sld xmlns:p="p" xmlns:a="a"><a:t>Opening lesson</a:t></p:sld>');
    $archive->close();

    try {
        $binary = file_get_contents($path);
        $text = app(KnowledgeService::class)->extractTextFromBinary($binary, 'pptx');

        expect($text)
            ->toContain('[Slide 1]')
            ->toContain('Opening lesson')
            ->toContain('[Slide 2]')
            ->toContain('Second slide');
    } finally {
        @unlink($path);
    }
});

it('provides a safe format-specific explanation when a file has no readable text', function () {
    expect(app(KnowledgeService::class)->unreadableDocumentHint('docx'))
        ->toContain('Word')
        ->toContain('ảnh scan');
});
