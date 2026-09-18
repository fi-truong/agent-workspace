<?php

use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('keeps the homepage guide hidden by default', function () {
    $this->get(route('ai-plus.index'))
        ->assertOk()
        ->assertDontSee('Ask AI Plus Guide');
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
        ->assertSee('Ask AI Plus Guide');
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
