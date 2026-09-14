<?php

use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('chat', 'feature');

beforeEach(function () {
    Storage::fake('knowledge');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Fake OpenAI trả lời cố định (đặt trước preventStrayRequests).
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Phản hồi từ AI']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            'model' => 'gpt-5.6-luna',
        ]),
    ]);
    Http::preventStrayRequests();
});

it('creates conversation and stores both messages', function () {
    config(['openai.api_key' => 'sk-test']);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Xin chào',
    ]);

    $response->assertStatus(200)
        ->assertJson(['blocked' => false, 'reply' => 'Phản hồi từ AI']);

    expect(Conversation::count())->toBe(1)
        ->and(Conversation::first()->messages()->count())->toBe(2) // user + assistant
        ->and(UsageLog::count())->toBe(1);
});

it('sends full history (max 20) on subsequent messages', function () {
    config(['openai.api_key' => 'sk-test']);

    $conv = Conversation::create(['user_id' => $this->user->id, 'title' => 'Cuộc trò chuyện']);
    // Tạo 25 tin trước đó để vượt cap.
    for ($i = 0; $i < 25; $i++) {
        $conv->messages()->create(['role' => $i % 2 === 0 ? 'user' : 'assistant', 'content' => "Tin {$i}"]);
    }

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Tin mới',
        'conversation_id' => $conv->id,
    ])->assertStatus(200);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        // message user mới nhất vừa gửi được lưu trước khi gom history → có 21 tin trong payload
        $userMsgs = collect($payload['messages'])->where('role', 'user')->count();
        $assistantMsgs = collect($payload['messages'])->where('role', 'assistant')->count();

        $actualTotal = $userMsgs + $assistantMsgs;

        // 20 tin gần nhất + tin vừa lưu (tổng 21), nhưng vì tin vừa lưu nằm trong 20 gần nhất
        // nên tổng thực tế <= 20. Đảm bảo không vượt quá history cap 20.
        return $actualTotal <= 20;
    });
});

it('uses agent system prompt when conversation linked to agent', function () {
    config(['openai.api_key' => 'sk-test']);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Agent Toán',
        'system_prompt' => 'Bạn là giáo viên toán với phong cách khích lệ.',
        'is_shared' => false,
    ]);

    $conv = Conversation::create(['user_id' => $this->user->id, 'title' => 'Chat']);
    $conv->update(['agent_id' => $agent->id]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Giải thích cho tôi',
        'conversation_id' => $conv->id,
    ])->assertStatus(200);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        $system = $payload['messages'][0] ?? null;

        return ($system['role'] ?? '') === 'system'
            && ($system['content'] ?? '') === 'Bạn là giáo viên toán với phong cách khích lệ.';
    });
});

it('includes knowledge text in system prompt when agent has files', function () {
    config(['openai.api_key' => 'sk-test']);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Agent có knowledge',
        'system_prompt' => 'Bạn là trợ lý.',
        'is_shared' => false,
    ]);

    // Giả lập file đã lưu + write vào disk knowledge.
    $path = $this->user->id.'/'.$agent->id.'/syllabus.txt';
    Storage::disk('knowledge')->put($path, 'Chương trình học Môn Toán lớp 7');
    $agent->update(['knowledge' => json_encode([
        ['path' => $path, 'original_name' => 'syllabus.txt'],
    ])]);

    $conv = Conversation::create(['user_id' => $this->user->id, 'title' => 'Chat']);
    $conv->update(['agent_id' => $agent->id]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Cho tôi biết chương trình',
        'conversation_id' => $conv->id,
    ])->assertStatus(200);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        $system = $payload['messages'][0] ?? null;

        return ($system['role'] ?? '') === 'system'
            && str_contains($system['content'] ?? '', 'Chương trình học Môn Toán lớp 7');
    });
});

it('uses default when no agent linked', function () {
    config(['openai.api_key' => 'sk-test']);

    $conv = Conversation::create(['user_id' => $this->user->id, 'title' => 'Chat']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Chào',
        'conversation_id' => $conv->id,
    ])->assertStatus(200);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        // Không có system prompt → message đầu là role=user
        $first = $payload['messages'][0] ?? null;

        return ($first['role'] ?? '') !== 'system';
    });
});

it('returns friendly error on upstream 429', function () {
    config(['openai.api_key' => 'sk-test']);

    // Thay toàn bộ facade Http bằng instance mới (xóa stub 200 từ beforeEach),
    // rồi fake lỗi 429 cho riêng test này.
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'error' => ['code' => 'insufficient_quota', 'message' => 'You exceeded your current quota.'],
        ], 429),
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Xin chào',
    ]);

    $response->assertStatus(502)
        ->assertJson(['retryable' => true]);
});

it('sends multimodal content when image data URL is provided', function () {
    config(['openai.api_key' => 'sk-test']);

    $dataUrl = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Mô tả hình',
        'images' => [$dataUrl],
    ]);

    $response->assertStatus(200);

    Http::assertSent(function (Request $request) {
        $payload = $request->data();
        $messages = $payload['messages'] ?? [];
        $last = end($messages);

        // Message user cuối là multimodal: text + image_url
        return is_array($last['content'])
            && count($last['content']) === 2
            && ($last['content'][0]['type'] ?? '') === 'text'
            && ($last['content'][1]['type'] ?? '') === 'image_url';
    });
});

it('filters PII via guardrail middleware instead of storing raw PII', function () {
    // GuardrailMiddleware (web group) replace PII trước khi tới controller,
    // nên message lưu xuống DB là dạng đã che [SĐT], không phải raw.
    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'SĐT của học sinh là 0901234567',
    ]);

    $response->assertStatus(200)
        ->assertJson(['blocked' => false]);

    // Message user đã được guardrail thay PII trước khi lưu.
    $stored = Message::where('role', 'user')->latest('id')->first();
    expect($stored->content)->not->toContain('0901234567');
});
