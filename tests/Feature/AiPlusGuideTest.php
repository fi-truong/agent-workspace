<?php

use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('keeps the homepage guide hidden by default', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('ai-plus.index'))
        ->assertOk()
        ->assertDontSee('Ask AI Plus Guide')
        ->assertSee('data-theme-value="mint"', false)
        ->assertSee('data-theme-value="teal"', false)
        ->assertSee('data-theme-value="orange"', false)
        ->assertSee('data-theme-value="burgundy"', false)
        ->assertSee('body:has(.ai-guide) .theme-switcher{left:20px;right:auto;}', false);

    $styles = file_get_contents(public_path('css/ai-plus.css'));

    expect($styles)
        ->toContain('[data-theme="orange"] .crumbbar .role-pill{background:rgba(242,138,53,.18);')
        ->toContain('[data-theme="burgundy"] .crumbbar .role-pill{background:rgba(216,163,106,.16);');
});

it('lets an administrator control the homepage guide visibility', function () {
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.ai-plus-guide.update'), ['enabled' => '1'])
        ->assertRedirect(route('admin.ai-plus-guide.index'));

    expect(AppSetting::boolean('ai_plus_homepage_guide_enabled'))->toBeTrue()
        ->and(AdminAuditLog::where('event', 'ai_plus_guide.visibility_updated')->exists())->toBeTrue();

    $this->get(route('ai-plus.index'))
        ->assertOk()
        ->assertSee('Ask AI Plus Guide')
        ->assertSee('.ai-guide-form textarea{', false)
        ->assertSee('color:#1b2e2a;background:#fff', false)
        ->assertSee('.ai-guide-form textarea::placeholder{color:#657772', false)
        ->assertSee('data-theme-src-orange=', false)
        ->assertSee('ai-plus-guide-bot-orange.png')
        ->assertSee('data-theme-src-burgundy=', false)
        ->assertSee('ai-plus-guide-bot-burgundy.png')
        ->assertSee('updateGuideBotTheme(button.dataset.themeValue)', false);
});

it('answers guide questions for signed-in users and records token usage', function () {
    AppSetting::create(['key' => 'ai_plus_homepage_guide_enabled', 'value' => 'true']);
    $user = User::factory()->create();
    config(['openai.api_key' => 'sk-test']);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Mở Agent Workspace tại /ai-plus/agent-workspace để bắt đầu.']]],
            'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 9],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('ai-plus.guide.reply'), ['message' => 'Tôi muốn tạo một agent'])
        ->assertOk()
        ->assertJsonPath('reply', 'Mở Agent Workspace tại /ai-plus/agent-workspace để bắt đầu.');

    expect(UsageLog::where('user_id', $user->id)->value('source'))->toBe('ai_plus_guide');
});

it('does not answer guide questions while the guide is hidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai-plus.guide.reply'), ['message' => 'Tôi muốn tạo một agent'])
        ->assertNotFound();
});
