<?php

use App\Models\AdminAuditLog;
use App\Models\Agent;
use App\Models\AiArtifact;
use App\Models\AiSafetyEvent;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\EmailDraft;
use App\Models\KnowledgeChunk;
use App\Models\Message;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\KnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('chat', 'feature');

beforeEach(function () {
    Storage::fake('knowledge');
    Storage::fake('ai-artifacts');
    Storage::fake('chat-attachments');

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
        ->and(Conversation::first()->type)->toBe(Conversation::TYPE_CHAT)
        ->and(Conversation::first()->messages()->count())->toBe(2) // user + assistant
        ->and(UsageLog::count())->toBe(1);
});

it('generates and stores an image when the administrator enables the feature', function () {
    config(['openai.api_key' => 'sk-test']);
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'data' => [['b64_json' => base64_encode('fake-png-image')]],
            'usage' => ['input_tokens' => 11, 'output_tokens' => 22],
        ]),
    ]);

    $response = $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'prompt' => 'A friendly robot reading a book',
    ]);

    $response->assertOk()->assertJsonPath('prompt', 'A friendly robot reading a book');
    $conversation = Conversation::firstOrFail();
    expect($conversation->type)->toBe(Conversation::TYPE_IMAGE)
        ->and($conversation->messages)->toHaveCount(2)
        ->and($conversation->messages->last()->content)->toContain('Generated image');
    Storage::disk('chat-attachments')->assertExists($conversation->id.'/'.basename(parse_url($response->json('image_url'), PHP_URL_PATH)));
    expect(UsageLog::latest('id')->value('completion_tokens'))->toBe(22);
});

it('does not expose image generation when an administrator has disabled it', function () {
    $this->postJson(route('ai-plus.agent-workspace.generate-image'), ['prompt' => 'A robot'])
        ->assertNotFound();
});

it('uses the image edits endpoint when reference images are supplied', function () {
    config(['openai.api_key' => 'sk-test']);
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'data' => [['b64_json' => base64_encode('edited-png-image')]],
            'usage' => ['input_tokens' => 19, 'output_tokens' => 22],
        ]),
    ]);

    $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'prompt' => 'Replace the background with a library',
        'reference_images' => ['data:image/png;base64,'.base64_encode('source-png-image')],
    ])->assertOk();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/edits')
        && str_contains($request->body(), 'gpt-image-2.5-sunburst'));
    expect(Message::where('role', 'user')->value('content'))->toStartWith('🖼️ Edit image:');
});

it('creates a new image version from a previously generated image in the same session', function () {
    config(['openai.api_key' => 'sk-test']);
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'data' => [['b64_json' => base64_encode('version-image')]],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 24],
        ]),
    ]);

    $first = $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'prompt' => 'A student studying in a library',
    ])->assertOk();

    $second = $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'conversation_id' => $first->json('conversation_id'),
        'source_message_id' => $first->json('image_message_id'),
        'prompt' => 'Change the background to a modern classroom',
    ])->assertOk();

    expect($second->json('conversation_id'))->toBe($first->json('conversation_id'));
    expect(Conversation::findOrFail($first->json('conversation_id'))->messages)->toHaveCount(4);
    expect(Message::where('role', 'assistant')->count())->toBe(2);
    expect(UsageLog::latest('id')->value('source_message_id'))->toBe($first->json('image_message_id'));
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/edits')
        && str_contains($request->body(), 'gpt-image-2.5-sunburst'));
});

it('does not allow a user to edit another users generated image', function () {
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    $otherUser = User::factory()->create();
    $conversation = Conversation::create([
        'user_id' => $otherUser->id,
        'title' => 'Private image session',
        'type' => Conversation::TYPE_IMAGE,
    ]);
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => '![Generated image](/ai-plus/agent-workspace/attachments/'.$conversation->id.'/private.png)',
    ]);

    $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'prompt' => 'Change this image',
        'source_message_id' => $message->id,
    ])->assertNotFound();
});

it('rejects an image model which an administrator has disabled', function () {
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    AppSetting::create(['key' => 'ai_plus_image_flare_enabled', 'value' => 'false']);

    $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'prompt' => 'A robot',
        'model' => 'gpt-image-2.5-flare',
    ])->assertUnprocessable()
        ->assertJsonPath('error', 'The selected image model is unavailable. Choose an enabled model and try again.');
});

it('does not allow image generation after the monthly token quota is exhausted', function () {
    config(['usage.token_limits.testing' => 1]);
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    UsageLog::create([
        'user_id' => $this->user->id,
        'activity_title' => 'Existing usage',
        'source' => 'agent_workspace',
        'prompt_tokens' => 1,
    ]);

    $this->postJson(route('ai-plus.agent-workspace.generate-image'), ['prompt' => 'A robot'])
        ->assertStatus(429)
        ->assertJsonPath('retryable', false);

    expect(Conversation::count())->toBe(0);
});

it('lets an owner download and remove one generated image without losing token accounting', function () {
    config(['openai.api_key' => 'sk-test']);
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'data' => [['b64_json' => base64_encode('fake-png-image')]],
            'usage' => ['input_tokens' => 11, 'output_tokens' => 22],
        ]),
    ]);

    $created = $this->postJson(route('ai-plus.agent-workspace.generate-image'), ['prompt' => 'An autumn campus'])->assertOk();
    $imageMessage = Message::where('role', 'assistant')->firstOrFail();
    $filePath = $imageMessage->conversation_id.'/'.basename(parse_url($created->json('image_url'), PHP_URL_PATH));

    $this->get(route('ai-plus.agent-workspace.images.download', $imageMessage))->assertOk();
    $this->deleteJson(route('ai-plus.agent-workspace.images.destroy', $imageMessage))->assertOk()
        ->assertJsonPath('conversation_deleted', true);

    Storage::disk('chat-attachments')->assertMissing($filePath);
    expect(Conversation::count())->toBe(0)
        ->and(UsageLog::firstOrFail()->hidden_at)->not->toBeNull()
        ->and(UsageLog::firstOrFail()->related_conversation_id)->toBeNull()
        ->and(UsageLog::firstOrFail()->completion_tokens)->toBe(22);
});

it('deletes all private image files when an owner deletes an image session', function () {
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Image session',
        'type' => Conversation::TYPE_IMAGE,
    ]);
    Storage::disk('chat-attachments')->put($conversation->id.'/generated.png', 'private-image');
    UsageLog::create([
        'user_id' => $this->user->id,
        'activity_title' => 'Image: Session',
        'source' => 'agent_workspace',
        'related_conversation_id' => $conversation->id,
        'prompt_tokens' => 10,
    ]);

    $this->deleteJson(route('ai-plus.conversations.destroy', $conversation))->assertOk();

    Storage::disk('chat-attachments')->assertMissing($conversation->id.'/generated.png');
    expect(Conversation::find($conversation->id))->toBeNull()
        ->and(UsageLog::firstOrFail()->hidden_at)->not->toBeNull();
});

it('shows a dedicated image workspace only when image generation is enabled', function () {
    AppSetting::create(['key' => 'ai_plus_image_generation_enabled', 'value' => 'true']);

    $this->get(route('ai-plus.agent-workspace.images.index'))
        ->assertOk()
        ->assertSee('Image Studio')
        ->assertSee('Create an image');

    AppSetting::query()->where('key', 'ai_plus_image_generation_enabled')->update(['value' => 'false']);
    $this->get(route('ai-plus.agent-workspace.images.index'))->assertNotFound();
});

it('creates a requested Excel artifact and returns an authorized download URL', function () {
    config(['openai.api_key' => 'sk-test']);

    $response = $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Hãy tạo file Excel từ nội dung này']);

    $response->assertOk()->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.xlsx'));
    $artifact = AiArtifact::firstOrFail();
    Storage::disk('ai-artifacts')->assertExists($artifact->path);
    expect(AdminAuditLog::where('event', 'ai_artifact.created')->exists())->toBeTrue();
    $this->get(route('ai-plus.artifacts.download', $artifact))->assertOk();
});

it('creates requested Word and PDF artifacts', function (string $request, string $extension) {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', ['message' => $request])->assertOk();

    expect(AiArtifact::firstOrFail()->name)->toEndWith($extension);
})->with([
    ['Hãy tạo file Word cho nội dung này', '.docx'],
    ['Hãy xuất PDF cho nội dung này', '.pdf'],
]);

it('creates an HTML artifact from the AI code block', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => "Updated page:\n```html\n<h1>Updated</h1>\n```"]]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            'model' => 'gpt-5.6-luna',
        ]),
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Hãy sửa file HTML và tạo file HTML'])
        ->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.html'));

    $artifact = AiArtifact::firstOrFail();
    expect(Storage::disk('ai-artifacts')->get($artifact->path))->toBe('<h1>Updated</h1>');
});

it('creates an email draft but never sends an email', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Soạn email nháp thông báo họp'])
        ->assertOk()->assertJsonStructure(['email_draft' => ['id', 'subject', 'body']]);

    expect(EmailDraft::count())->toBe(1);
    expect(AdminAuditLog::where('event', 'email_draft.created')->exists())->toBeTrue();
});

it('lists conversations by their most recent update in the workspace sidebar', function () {
    $recentlyCreated = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Tạo sau nhưng không hoạt động',
    ]);
    $recentlyUpdated = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Tạo trước nhưng vừa nhắn',
    ]);

    $recentlyCreated->forceFill(['updated_at' => now()->subHour()])->saveQuietly();
    $recentlyUpdated->forceFill(['updated_at' => now()])->saveQuietly();

    $this->get(route('ai-plus.agent-workspace.index'))
        ->assertOk()
        ->assertViewHas('conversations', function ($conversations) use ($recentlyUpdated, $recentlyCreated) {
            return array_column($conversations, 'id') === [$recentlyUpdated->id, $recentlyCreated->id];
        });
});

it('preserves an internal LSTS email in a standalone chat', function () {
    config(['openai.api_key' => 'sk-test']);
    $email = 'ciec.coordinator.04@lsts.edu.vn';

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => "Hãy gửi báo cáo cho {$email}",
    ])->assertOk()->assertJson(['blocked' => false]);

    expect(Message::where('role', 'user')->latest('id')->value('content'))->toContain($email);

    Http::assertSent(fn (Request $request) => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        $email,
    ));
});

it('preserves an internal LSTS email in an Agent chat', function () {
    config(['openai.api_key' => 'sk-test']);
    $email = 'ciec.coordinator.04@lsts.edu.vn';
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Agent nội bộ',
        'system_prompt' => 'Bạn là trợ lý nội bộ.',
        'is_shared' => false,
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => "Hãy gửi báo cáo cho {$email}",
        'agent_id' => $agent->id,
    ])->assertOk()->assertJson(['blocked' => false]);

    expect(Message::where('role', 'user')->latest('id')->value('content'))->toContain($email)
        ->and(Conversation::latest('id')->value('agent_id'))->toBe($agent->id);

    Http::assertSent(fn (Request $request) => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        $email,
    ));
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
            && str_contains((string) ($system['content'] ?? ''), 'AI+ is an LSTS workplace assistant.')
            && str_contains((string) ($system['content'] ?? ''), 'Bạn là giáo viên toán với phong cách khích lệ.');
    });
});

it('records work-use scope metadata without retaining prompt content', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Create a travel itinerary for my personal vacation.',
    ])->assertOk();

    $event = AiSafetyEvent::latest()->firstOrFail();

    expect($event->user_id)->toBe($this->user->id)
        ->and($event->classification)->toBe('personal_or_unrelated')
        ->and($event->action)->toBe('allowed')
        ->and($event->metadata)->toHaveKey('input_length')
        ->and(json_encode($event->metadata))->not->toContain('vacation');
});

it('does not retain routine school-work requests in the work-use review queue', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Create a lesson plan for Grade 7 science.',
    ])->assertOk();

    expect(AiSafetyEvent::count())->toBe(0);
});

it('blocks high-confidence unsafe input before it is sent to the chat model', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Tôi muốn tự làm hại bản thân, hãy hướng dẫn tôi làm việc đó.',
    ])->assertUnprocessable()
        ->assertJsonPath('blocked', true)
        ->assertJsonPath('error', 'This request cannot be processed because it may violate AI+ safety guidelines.');

    expect(AiSafetyEvent::latest()->firstOrFail()->moderation_flagged)->toBeTrue()
        ->and(AiSafetyEvent::latest()->firstOrFail()->category)->toBe('self_harm');

    Http::assertNothingSent();
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

it('uses indexed RAG context for an agent conversation', function () {
    config([
        'openai.api_key' => 'sk-test',
        'openai.rag_chunk_chars' => 30,
        'openai.rag_chunk_overlap' => 0,
        'openai.rag_top_k' => 1,
    ]);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Agent RAG',
        'system_prompt' => 'Bạn là trợ lý.',
        'is_shared' => false,
    ]);
    $path = $this->user->id.'/'.$agent->id.'/handbook.txt';
    Storage::disk('knowledge')->put($path, 'Thông tin chung. QUYTRINHDACBIET xử lý nghỉ phép. Thông tin khác.');
    $agent->update(['knowledge' => json_encode([
        ['path' => $path, 'original_name' => 'handbook.txt'],
    ])]);
    app(KnowledgeService::class)->indexAgent($agent);

    $conv = Conversation::create([
        'user_id' => $this->user->id,
        'agent_id' => $agent->id,
        'title' => 'Chat RAG',
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'QUYTRINHDACBIET là gì?',
        'conversation_id' => $conv->id,
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) data_get($request->data(), 'messages.0.content'),
        '=== KNOWLEDGE (đoạn liên quan nhất đến câu hỏi, RAG) ===',
    ));
});

it('uses semantic RAG when embeddings are available', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_top_k' => 1]);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Semantic RAG',
        'is_shared' => false,
    ]);
    KnowledgeChunk::create([
        'agent_id' => $agent->id,
        'source_file' => 'handbook.txt',
        'chunk_index' => 0,
        'content' => 'Quy trình xin nghỉ phép dành cho nhân viên.',
        'embedding' => [1.0, 0.0],
    ]);
    KnowledgeChunk::create([
        'agent_id' => $agent->id,
        'source_file' => 'handbook.txt',
        'chunk_index' => 1,
        'content' => 'Thực đơn căng tin được cập nhật mỗi tuần.',
        'embedding' => [0.0, 1.0],
    ]);

    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/embeddings')) {
            return Http::response(['data' => [['index' => 0, 'embedding' => [1.0, 0.0]]]]);
        }

        return Http::response([
            'choices' => [['message' => ['content' => 'Phản hồi từ AI']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
        ]);
    });

    $context = app(KnowledgeService::class)->retrieveContext($agent, 'How do staff request leave?');

    expect($context)->toContain('Quy trình xin nghỉ phép')
        ->not->toContain('Thực đơn căng tin');
});

it('uses semantic RAG for a direct chat attachment when embeddings are available', function () {
    config([
        'openai.api_key' => 'sk-test',
        'openai.rag_chunk_chars' => 35,
        'openai.rag_chunk_overlap' => 0,
        'openai.rag_top_k' => 1,
    ]);

    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/embeddings')) {
            $input = $request->data()['input'];

            return Http::response([
                'data' => collect($input)->map(fn ($_text, int $index) => [
                    'index' => $index,
                    'embedding' => $index <= 1 ? [1.0, 0.0] : [0.0, 1.0],
                ])->all(),
            ]);
        }

        return Http::response([
            'choices' => [['message' => ['content' => 'Phản hồi từ AI']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
        ]);
    });

    $context = app(KnowledgeService::class)->retrieveInlineContext(
        'Quy trình xin nghỉ phép dành cho nhân viên. Thực đơn căng tin được cập nhật mỗi tuần.',
        'How do staff request leave?',
        'handbook.txt',
    );

    expect($context)->toContain('Quy trình xin nghỉ phép')
        ->not->toContain('Thực đơn căng tin');
});

it('falls back to keyword RAG when the embedding request fails', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_top_k' => 1]);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Fallback RAG',
        'is_shared' => false,
    ]);
    KnowledgeChunk::create([
        'agent_id' => $agent->id,
        'source_file' => 'handbook.txt',
        'chunk_index' => 0,
        'content' => 'QUYTRINHDACBIET xử lý nghỉ phép.',
        'embedding' => [1.0, 0.0],
    ]);
    KnowledgeChunk::create([
        'agent_id' => $agent->id,
        'source_file' => 'handbook.txt',
        'chunk_index' => 1,
        'content' => 'Thông tin không liên quan.',
        'embedding' => [0.0, 1.0],
    ]);

    Http::fake(['https://api.openai.com/*' => Http::response(['error' => ['message' => 'Unavailable']], 503)]);

    $context = app(KnowledgeService::class)->retrieveContext($agent, 'QUYTRINHDACBIET là gì?');

    expect($context)->toContain('QUYTRINHDACBIET xử lý nghỉ phép')
        ->not->toContain('Thông tin không liên quan');
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
        // All workspace conversations include the school-work policy.
        $first = $payload['messages'][0] ?? null;

        return ($first['role'] ?? '') === 'system'
            && str_contains((string) ($first['content'] ?? ''), 'AI+ is an LSTS workplace assistant.');
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

it('includes text extracted from a direct document attachment in the chat request', function () {
    config(['openai.api_key' => 'sk-test']);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Tóm tắt tài liệu này',
        'documents' => [[
            'name' => 'lesson-plan.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode('Nội dung kế hoạch bài học'),
        ]],
    ]);

    $response->assertStatus(200)
        ->assertJson(['blocked' => false, 'reply' => 'Phản hồi từ AI']);

    expect(Message::where('role', 'user')->latest('id')->value('content'))
        ->toContain('lesson-plan.txt')
        ->toContain('Nội dung kế hoạch bài học');

    Http::assertSent(function (Request $request) {
        return str_contains(
            (string) data_get($request->data(), 'messages.0.content'),
            'Nội dung kế hoạch bài học',
        );
    });
});

it('uses RAG to select relevant text from a direct chat attachment', function () {
    config([
        'openai.api_key' => 'sk-test',
        'openai.rag_chunk_chars' => 30,
        'openai.rag_chunk_overlap' => 0,
        'openai.rag_top_k' => 1,
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'QUYTRINHDACBIET là gì?',
        'documents' => [[
            'name' => 'handbook.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode(
                'Thông tin chung. QUYTRINHDACBIET xử lý nghỉ phép. Thông tin khác.',
            ),
        ]],
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) data_get($request->data(), 'messages.0.content'),
        'QUYTRINHDACBIET xử lý',
    ));
});

it('includes every direct document attachment, including when an image is attached', function () {
    config(['openai.api_key' => 'sk-test']);

    $documents = collect(['word', 'excel', 'pdf'])->map(fn (string $name) => [
        'name' => $name.'.txt',
        'data_url' => 'data:text/plain;base64,'.base64_encode(str_repeat($name.' content ', 300)),
    ])->all();

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'So sánh các tệp đính kèm',
        'images' => ['data:image/png;base64,'.base64_encode('fake-png-bytes')],
        'documents' => $documents,
    ])->assertOk();

    Http::assertSent(function (Request $request) {
        $messages = $request->data()['messages'] ?? [];
        $lastMessage = end($messages);
        $content = $lastMessage['content'] ?? [];
        $text = is_array($content) ? (string) data_get($content, '0.text') : (string) $content;

        return is_array($content)
            && data_get($content, '1.type') === 'image_url'
            && str_contains($text, 'Đính kèm 1 hình ảnh')
            && str_contains($text, 'word content')
            && str_contains($text, 'excel content')
            && str_contains($text, 'pdf content');
    });
});

it('rejects an attachment payload that exceeds the total size limit before starting a chat', function () {
    $largeDocument = 'data:text/plain;base64,'.base64_encode(str_repeat('a', 5 * 1024 * 1024));

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Read these files',
        'documents' => collect(range(1, 4))->map(fn () => [
            'name' => 'large.txt',
            'data_url' => $largeDocument,
        ])->all(),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Tổng dung lượng tệp trong một lượt chat chỉ được tối đa 15 MB.');
    expect(Conversation::count())->toBe(0);
});

it('rejects more than four images before starting a chat', function () {
    $image = 'data:image/png;base64,'.base64_encode('fake-png-bytes');

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Describe these images',
        'images' => array_fill(0, 5, $image),
    ])->assertStatus(422)->assertJsonValidationErrors('images');

    expect(Conversation::count())->toBe(0);
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
