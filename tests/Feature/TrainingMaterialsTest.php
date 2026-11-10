<?php

use App\Models\User;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('training materials require sign in', function () {
    $this->get('/ai-plus/support/training-materials/2026-11-12')
        ->assertRedirect(route('login.local.form'));
});

test('training downloads follow the existing AI policy acceptance requirement', function () {
    $this->actingAs(User::factory()->withoutAiPolicyAcceptance()->create())
        ->get('/ai-plus/support/training-materials/2026-11-12')
        ->assertRedirect(route('ai-plus.policy-acceptance.show'));
});

test('support and homepage do not display the training materials link', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('ai-plus.support.index'))
        ->assertOk()
        ->assertSee('HSC, Open Office')
        ->assertDontSee('Tài liệu tập huấn AI+ — 12/11')
        ->assertDontSee(route('ai-plus.support.training-materials.download'), false);

    $this->get(route('ai-plus.index'))
        ->assertOk()
        ->assertDontSee('Tài liệu tập huấn AI+ — 12/11');
});

test('signed in users download the complete sixteen agent training package', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('ai-plus.support.training-materials.download'))
        ->assertOk()
        ->assertDownload('CIEC_26_1110_Bo_tai_lieu_16_Agent_AI_plus.zip')
        ->assertHeader('Content-Type', 'application/zip')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    $archive = new ZipArchive;
    expect($archive->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    $wordFiles = 0;
    $knowledgeFiles = 0;
    $hasGuide = false;

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = $archive->getNameIndex($index);
        $wordFiles += str_ends_with($name, '.docx') ? 1 : 0;
        $knowledgeFiles += str_contains($name, '/Knowledge/') && str_ends_with($name, '.txt') ? 1 : 0;
        $hasGuide = $hasGuide || str_ends_with($name, '/Huong_dan_chon_bo_mau.txt');
    }

    expect($wordFiles)->toBe(16)
        ->and($knowledgeFiles)->toBe(47)
        ->and($hasGuide)->toBeTrue();
    $archive->close();
});

test('a missing training package returns not found', function () {
    File::partialMock()
        ->shouldReceive('isFile')
        ->once()
        ->with(resource_path('training/CIEC_26_1110_Bo_tai_lieu_16_Agent_AI_plus.zip'))
        ->andReturnFalse();

    $this->actingAs(User::factory()->create())
        ->get(route('ai-plus.support.training-materials.download'))
        ->assertNotFound();
});
