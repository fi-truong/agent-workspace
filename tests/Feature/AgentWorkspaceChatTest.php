<?php

use App\Models\AdminAuditLog;
use App\Models\Agent;
use App\Models\AiArtifact;
use App\Models\AiSafetyEvent;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\EmailDraft;
use App\Models\KnowledgeChunk;
use App\Models\SchoolKnowledgeChunk;
use App\Models\SchoolKnowledgeSource;
use App\Models\Message;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\KnowledgeService;
use App\Services\ArtifactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class)->group('chat', 'feature');

function codePatchToolCallResponse(string $callId, string $summary, array $edits, int $promptTokens = 20, int $completionTokens = 8): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => $callId,
                    'type' => 'function',
                    'function' => [
                        'name' => 'apply_code_patch',
                        'arguments' => json_encode(['summary' => $summary, 'edits' => $edits]),
                    ],
                ]],
            ],
        ]],
        'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens],
        'model' => 'gpt-6-luna',
    ];
}

function artifactToolCallResponse(string $format, string $content, string $summary = 'File ready.', string $filename = '', string $extension = '', int $promptTokens = 20, int $completionTokens = 8): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-artifact',
                    'type' => 'function',
                    'function' => [
                        'name' => 'create_ai_artifact',
                        'arguments' => json_encode([
                            'format' => $format,
                            'file_extension' => $extension,
                            'filename' => $filename,
                            'content' => $content,
                            'summary' => $summary,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ],
        ]],
        'usage' => ['prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens],
        'model' => 'gpt-6-luna',
    ];
}

function fileActionConfirmationResponse(string $content = 'AI_PLUS_FILE_ACTION_CONFIRMED'): array
{
    return [
        'choices' => [['message' => ['content' => $content]]],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2],
        'model' => 'gpt-6-luna',
    ];
}

function confirmedArtifactResponseSequence(string $format, string $content, string $summary = 'File ready.', string $filename = '', string $extension = '', int $promptTokens = 20, int $completionTokens = 8): \Illuminate\Http\Client\ResponseSequence
{
    return Http::sequence()
        ->push(artifactToolCallResponse($format, $content, $summary, $filename, $extension, $promptTokens, $completionTokens))
        ->push(fileActionConfirmationResponse());
}

function confirmedCodePatchResponseSequence(array ...$patchResponses): \Illuminate\Http\Client\ResponseSequence
{
    $sequence = Http::sequence();
    foreach ($patchResponses as $index => $patchResponse) {
        if ($index === 1) {
            $sequence->push(fileActionConfirmationResponse());
        }
        $sequence->push($patchResponse);
    }

    if (count($patchResponses) === 1) {
        $sequence->push(fileActionConfirmationResponse());
    }

    return $sequence;
}

function emailDraftToolCallResponse(string $subject, string $body, string $summary = 'Draft saved.'): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-email-draft',
                    'type' => 'function',
                    'function' => [
                        'name' => 'save_email_draft',
                        'arguments' => json_encode(compact('subject', 'body') + ['summary' => $summary], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ],
        ]],
        'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 8],
        'model' => 'gpt-6-luna',
    ];
}

function reviseArtifactToolCallResponse(int $artifactId = 0, string $uploadMessage = 'Please upload the original file so I can revise it.'): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-revise-artifact',
                    'type' => 'function',
                    'function' => [
                        'name' => 'revise_ai_artifact',
                        'arguments' => json_encode([
                            'artifact_id' => $artifactId,
                            'if_unavailable_message' => $uploadMessage,
                            'summary' => 'I will revise the saved document.',
                        ]),
                    ],
                ]],
            ],
        ]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 4],
        'model' => 'gpt-6-luna',
    ];
}

function requestSourceFileToolCallResponse(string $message): array
{
    return [
        'choices' => [[
            'message' => [
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call-request-source',
                    'type' => 'function',
                    'function' => [
                        'name' => 'request_source_file',
                        'arguments' => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ],
        ]],
        'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 6],
        'model' => 'gpt-6-luna',
    ];
}

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
            'model' => 'gpt-6-luna',
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

it('recovers the complete saved reply for a streamed turn without exposing other turns', function () {
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Translation',
        'type' => Conversation::TYPE_CHAT,
    ]);
    $prompt = Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Translate UGC']);

    $this->getJson(route('ai-plus.agent-workspace.messages.reply', $prompt))
        ->assertStatus(202)
        ->assertJson(['status' => 'pending']);

    Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Receive UGC services.']);
    $nextPrompt = Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Next question']);
    Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Next answer']);

    $this->getJson(route('ai-plus.agent-workspace.messages.reply', $prompt))
        ->assertOk()
        ->assertJsonPath('reply', 'Receive UGC services.')
        ->assertJsonPath('user_message_id', $prompt->id);
    $this->getJson(route('ai-plus.agent-workspace.messages.reply', $nextPrompt))
        ->assertOk()
        ->assertJsonPath('reply', 'Next answer');
});

it('keeps saved reply recovery private to the conversation owner', function () {
    $conversation = Conversation::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Private',
        'type' => Conversation::TYPE_CHAT,
    ]);
    $prompt = Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Private prompt']);
    Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Private answer']);

    $this->getJson(route('ai-plus.agent-workspace.messages.reply', $prompt))->assertNotFound();
});

it('replaces only the newest user prompt and regenerates its reply', function () {
    config(['openai.api_key' => 'sk-test']);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Bản nháp ban đầu',
    ])->assertOk();

    $conversation = Conversation::firstOrFail();
    $prompt = $conversation->messages()->where('role', 'user')->firstOrFail();

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Bản nháp đã chỉnh sửa',
        'conversation_id' => $conversation->id,
        'edit_message_id' => $prompt->id,
    ])->assertOk()
        ->assertJsonPath('conversation_id', $conversation->id);

    expect($conversation->fresh()->messages()->count())->toBe(2)
        ->and($conversation->messages()->where('role', 'user')->sole()->content)->toBe('Bản nháp đã chỉnh sửa')
        ->and($conversation->messages()->where('role', 'assistant')->sole()->content)->toBe('Phản hồi từ AI');
});

it('exposes an edit control only for the newest text-only user prompt', function () {
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Editable prompt',
        'type' => Conversation::TYPE_CHAT,
    ]);
    $first = Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Prompt cũ']);
    Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Phản hồi cũ']);
    $latest = Message::create(['conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Prompt mới nhất']);
    Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Phản hồi mới nhất']);

    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()
        ->assertViewHas('initialMessages', function (array $messages) use ($first, $latest): bool {
            return $messages[0]['id'] === $first->id
                && $messages[0]['editable'] === false
                && $messages[2]['id'] === $latest->id
                && $messages[2]['editable'] === true;
        });
});

it('tells the model to call a structured tool to export requested files', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Hãy tạo file Word cho báo cáo này',
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        'AI+ creates downloadable DOCX (Word), XLSX (Excel), PPTX (PowerPoint), PDF, and HTML files through the create_ai_artifact tool',
    ) && collect($request->data()['tools'] ?? [])->contains(fn (array $tool): bool => ($tool['function']['name'] ?? '') === 'revise_ai_artifact'));
});

it('groups Agent conversations beneath their Agent in the workspace sidebar', function () {
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Admissions Assistant',
        'is_shared' => false,
    ]);
    Conversation::create(['user_id' => $this->user->id, 'title' => 'Quick question', 'type' => Conversation::TYPE_CHAT]);
    Conversation::create([
        'user_id' => $this->user->id,
        'agent_id' => $agent->id,
        'title' => 'Admissions chat',
        'type' => Conversation::TYPE_CHAT,
    ]);

    $this->get(route('ai-plus.agent-workspace.index'))
        ->assertOk()
        ->assertSeeInOrder(['Quick Chats', 'Quick question', 'My Agents', 'Admissions Assistant', 'Admissions chat']);
});

it('repairs a historic file message when the model incorrectly refused after creating it', function () {
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Exported plan',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Xin lỗi, tôi không có công cụ tạo file. 📄 **File ready:** [plan.docx](/ai-plus/artifacts/99/download)',
    ]);

    $this->get(route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation->id]))
        ->assertOk()
        ->assertViewHas('initialMessages', fn (array $messages): bool => str_starts_with(
            $messages[0]['content'] ?? '',
            'Đã tạo file từ nội dung liên quan trong cuộc chat này.',
        ));
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
        'prompt' => 'A wide landscape illustration of a friendly robot reading a book',
    ]);

    $response->assertOk()->assertJsonPath('prompt', 'A wide landscape illustration of a friendly robot reading a book');
    $conversation = Conversation::firstOrFail();
    expect($conversation->type)->toBe(Conversation::TYPE_IMAGE)
        ->and($conversation->messages)->toHaveCount(2)
        ->and($conversation->messages->last()->content)->toContain('Generated image');
    Storage::disk('chat-attachments')->assertExists($conversation->id.'/'.basename(parse_url($response->json('image_url'), PHP_URL_PATH)));
    expect(UsageLog::latest('id')->value('completion_tokens'))->toBe(22);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/generations')
        && str_contains($request->body(), 'auto'));
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

    $sourceImage = imagecreatetruecolor(1200, 800);
    ob_start();
    imagepng($sourceImage);
    $sourceImageBytes = ob_get_clean();
    imagedestroy($sourceImage);

    $this->postJson(route('ai-plus.agent-workspace.generate-image'), [
        'prompt' => 'Replace the background with a library',
        'reference_images' => ['data:image/png;base64,'.base64_encode($sourceImageBytes)],
    ])->assertOk();

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/images/edits')
        && str_contains($request->body(), 'gpt-image-2.5-sunburst')
        && str_contains($request->body(), '1248x832'));
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
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence('excel', "# Budget\n\n| Item | Cost |\n| --- | --- |\n| Travel | 100 |", 'Budget workbook ready.')]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Hãy tạo file Excel từ nội dung này']);

    $response->assertOk()->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.xlsx'));
    $artifact = AiArtifact::firstOrFail();
    $downloadUrl = route('ai-plus.artifacts.download', $artifact, false);
    $response->assertJsonPath('reply', fn (string $reply) => str_contains($reply, $downloadUrl));
    expect(Message::query()->where('role', 'assistant')->latest('id')->value('content'))
        ->toContain($downloadUrl);
    Storage::disk('ai-artifacts')->assertExists($artifact->path);
    expect(AdminAuditLog::where('event', 'ai_artifact.created')->exists())->toBeTrue();
    $this->get(route('ai-plus.artifacts.download', $artifact))->assertOk();
});

it('creates a valid private PowerPoint artifact when requested', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence(
        'powerpoint',
        "# Energy Transition\n\n## Why it matters\n- Clean energy reduces emissions\n- Schools can model practical action\n\n## Next steps\n- Audit energy use\n- Choose one measurable improvement",
        'PowerPoint ready.',
        'energy-transition',
    )]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Create a PowerPoint about energy transition']);

    $response->assertOk()->assertJsonPath('artifacts.0.name', 'energy-transition.pptx');
    $artifact = AiArtifact::firstOrFail();
    expect($artifact->mime_type)->toBe('application/vnd.openxmlformats-officedocument.presentationml.presentation');
    Storage::disk('ai-artifacts')->assertExists($artifact->path);

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue()
        ->and($archive->locateName('ppt/presentation.xml'))->not->toBeFalse()
        ->and($archive->locateName('ppt/slides/slide1.xml'))->not->toBeFalse()
        ->and($archive->locateName('ppt/slides/slide3.xml'))->not->toBeFalse();
    expect($archive->getFromName('ppt/slides/slide2.xml'))->toContain('Why it matters');
    $archive->close();

    $this->get(route('ai-plus.artifacts.download', $artifact))->assertOk();
});

it('allows PowerPoint as a conversation export format', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => "# Chat summary\n\n## Key points\n- First point\n- Second point"]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
        'model' => 'gpt-6-luna',
    ])]);
    $conversation = Conversation::create(['user_id' => $this->user->id, 'title' => 'Export deck', 'type' => Conversation::TYPE_CHAT]);
    Message::create(['conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Here are the key points.']);

    $response = $this->postJson(route('ai-plus.conversations.export', $conversation), [
        'format' => 'powerpoint',
        'filename' => '',
        'language' => 'source',
        'template' => 'standard',
    ]);

    $response->assertOk()->assertJsonPath('name', fn (string $name) => str_ends_with($name, '.pptx'));
    expect(AiArtifact::firstOrFail()->mime_type)->toBe('application/vnd.openxmlformats-officedocument.presentationml.presentation');
});

it('creates requested Word and PDF artifacts', function (string $request, string $extension) {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    $format = $extension === '.pdf' ? 'pdf' : 'word';
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence($format, '# Deliverable', 'File ready.')]);

    $this->postJson('/ai-plus/agent-workspace/send', ['message' => $request])->assertOk();

    expect(AiArtifact::firstOrFail()->name)->toEndWith($extension);
})->with([
    ['Hãy tạo file Word cho nội dung này', '.docx'],
    ['Hãy xuất PDF cho nội dung này', '.pdf'],
]);

it('does not create a document for a question asking whether the previous file was updated', function () {
    config(['openai.api_key' => 'sk-test']);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'School calendar',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Hãy tạo file Word từ lịch năm học.',
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Mình sẽ tạo file Word mới cho bạn.',
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Bạn đã cập nhật trên đường link cũ hay bạn sẽ tạo file mới cho tôi?',
    ]);

    $response->assertOk()->assertJsonPath('artifacts', []);
    expect(AiArtifact::query()->count())->toBe(0);
});

it('creates a revised Word file when a follow-up changes a previously requested attached document', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false]);
    Http::swap(new Factory);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'School calendar',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Hãy tạo file Word thống kê lịch năm học. 📎 Tệp đính kèm: calendar.txt',
        'internal_context' => "📎 Tài liệu đính kèm đã nhận: calendar.txt\nTháng 9: Hoạt động riêng tại HSC. Tháng 10: Hoạt động riêng tại MSC. Tháng 11: Hoạt động chung hai cơ sở.",
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Đã tạo bản Word ban đầu.',
    ]);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence(
        'word',
        "# Lịch năm học đã cập nhật\n\n| Tháng | Ghi chú địa điểm |\n| --- | --- |\n| Tháng 9 | (H) |\n| Tháng 10 | (M) |\n| Tháng 11 | (joint) |",
        'Đã cập nhật file Word theo quy ước mới.',
    )]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Thay vì gộp lại thì ghi chú là "(H) and (M)" nếu tổ chức riêng ở 2 cơ sở, hoặc ghi (joint) nếu tổ chức cùng 1 hoạt động cho 2 cơ sở tham gia chung.',
    ]);

    $response->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.docx'))
        ->assertJsonPath('reply', fn (string $reply) => str_contains($reply, '/ai-plus/artifacts/'));

    $artifact = AiArtifact::query()->latest('id')->firstOrFail();
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue();
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($documentXml)
        ->toContain('Lịch năm học đã cập nhật')
        ->toContain('(joint)')
        ->not->toContain('Mình sẽ cập nhật file Word theo quy ước mới');
});

it('reuses a readable AI+ file in the same conversation when the user asks to revise it', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'School calendar',
        'type' => Conversation::TYPE_CHAT,
    ]);
    $originalContent = "# School calendar\n\n## September\n\nOpening assembly and orientation.";
    $original = app(ArtifactService::class)->generate($this->user, $conversation, 'word', $originalContent);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => '📄 **File ready:** ['.$original->name.'](/ai-plus/artifacts/'.$original->id.'/download)',
    ]);
    $revisedContent = "# School calendar\n\n## September\n\nOpening assembly, orientation, and parent meeting.";
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(reviseArtifactToolCallResponse($original->id))
        ->push(fileActionConfirmationResponse())
        ->push(artifactToolCallResponse('word', $revisedContent, 'The revised school calendar is ready.'))]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Please update the Word file linked above to include the parent meeting.',
    ])->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.docx'));

    expect(AiArtifact::query()->where('conversation_id', $conversation->id)->count())->toBe(2)
        ->and($response->json('reply'))->toContain('The revised school calendar is ready.');
    Http::assertSent(fn (Request $request): bool => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        'Opening assembly and orientation.',
    ));
});

it('asks the user to upload the source instead of creating a file when no saved artifact is available', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Unlinked document',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(reviseArtifactToolCallResponse(
            0,
            'Please upload the original Word file here so I can revise it.',
        ))
        ->push(fileActionConfirmationResponse())]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Please update the old Word file.',
    ])->assertOk()
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('reply', 'Please upload the original Word file here so I can revise it.');

    expect(AiArtifact::query()->exists())->toBeFalse();
    Http::assertSentCount(2);
});

it('asks the user to upload the original when a saved artifact contains only an acknowledgement', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Incomplete saved document',
        'type' => Conversation::TYPE_CHAT,
    ]);
    $acknowledgement = app(ArtifactService::class)->generate(
        $this->user,
        $conversation,
        'word',
        'I will revise the file and create a new document.',
    );
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(reviseArtifactToolCallResponse($acknowledgement->id))
        ->push(fileActionConfirmationResponse())
        ->push(requestSourceFileToolCallResponse('Mình chỉ tìm thấy lời hứa chỉnh sửa, chưa có nội dung tài liệu. Vui lòng tải file gốc lên để mình sửa chính xác.'))]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Please update this Word file with the new convention.',
    ])->assertOk()
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('reply', 'Mình chỉ tìm thấy lời hứa chỉnh sửa, chưa có nội dung tài liệu. Vui lòng tải file gốc lên để mình sửa chính xác.');

    expect(AiArtifact::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
    Http::assertSentCount(3);
});

it('generates full attached-document content instead of saving a short chat acknowledgement', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false]);
    Http::swap(new Factory);
    $documentText = 'BEGINNING_EVENT_MARKER'.str_repeat("\nTháng 9: CIEC tổ chức một hoạt động trong năm học.", 500)."\nFINAL_EVENT_MARKER";
    Http::fake(['https://api.openai.com/*' => function (Request $request) use ($documentText) {
        expect(json_encode($request->data(), JSON_UNESCAPED_UNICODE))->toContain('FINAL_EVENT_MARKER');

        if (! isset($request->data()['tools'])) {
            return Http::response(fileActionConfirmationResponse());
        }

        return Http::response(artifactToolCallResponse(
            'word',
            "# Lịch hoạt động\n\n| Tháng | Đơn vị | Sự kiện |\n| --- | --- | --- |\n| Tháng 9 | CIEC | BEGINNING_EVENT_MARKER |\n| Tháng 10 | UGC | FINAL_EVENT_MARKER |",
            'Đã tổng hợp đầy đủ lịch hoạt động.',
        ));
    }]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Dựa trên lịch đính kèm, hãy tạo file Word thống kê các sự kiện theo tháng và đơn vị.',
        'documents' => [[
            'name' => 'school-calendar.txt',
        'data_url' => 'data:text/plain;base64,'.base64_encode($documentText),
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.docx'));

    $artifact = AiArtifact::firstOrFail();
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue();
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($documentXml)
        ->toContain('Lịch hoạt động')
        ->toContain('BEGINNING_EVENT_MARKER')
        ->toContain('FINAL_EVENT_MARKER')
        ->not->toContain('đoạn thử nghiệm ngắn');

    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'FINAL_EVENT_MARKER')
        && ($request->data()['tools'][0]['function']['name'] ?? '') === 'create_ai_artifact');
});

it('keeps file-request acknowledgements hidden until the downloadable file is ready', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false]);
    Http::swap(new Factory);
    $arguments = json_encode(['format' => 'word', 'file_extension' => '', 'filename' => '', 'content' => "# Lịch năm học\n\n| Tháng | Đơn vị | Sự kiện |\n| --- | --- | --- |\n| Tháng 9 | CIEC | Hoạt động đầu năm |", 'summary' => 'Đã sắp xếp lịch.']);
    $streamEvents = [
        ['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call-artifact', 'type' => 'function', 'function' => ['name' => 'create_ai_artifact', 'arguments' => $arguments]]]], 'finish_reason' => null]], 'model' => 'gpt-6-luna'],
        ['choices' => [['delta' => [], 'finish_reason' => 'tool_calls']], 'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 15], 'model' => 'gpt-6-luna'],
    ];
    $streamBody = collect($streamEvents)
        ->map(fn (array $event): string => 'data: '.json_encode($event)."\n\n")
        ->push("data: [DONE]\n\n")
        ->implode('');
    Http::fake([
        'https://api.openai.com/*' => function (Request $request) use ($streamBody) {
            if (($request->data()['stream'] ?? false) === true) {
                return Http::response($streamBody, 200, ['Content-Type' => 'text/event-stream']);
            }

            if (! isset($request->data()['tools'])) {
                return Http::response(fileActionConfirmationResponse());
            }

            return Http::response(['choices' => [['message' => ['content' => 'Interactive Apps']]], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 25], 'model' => 'gpt-6-luna']);
        },
    ]);

    $response = $this->post('/ai-plus/agent-workspace/send-stream', [
        'message' => 'Đây là lịch năm học của trường, hãy tạo 1 file word mới để thống kê sự kiện theo đơn vị tổ chức.',
        'documents' => [[
            'name' => 'school-calendar.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode("Tháng 9: CIEC tổ chức hoạt động đầu năm.\nTháng 10: UGC tổ chức tư vấn đại học."),
        ]],
    ])->assertOk();

    $response->baseResponse->getCallback()();
    $artifact = AiArtifact::firstOrFail();
    expect($artifact->name)->toEndWith('.docx')
        ->and(Message::where('role', 'assistant')->sole()->content)->toContain('/ai-plus/artifacts/'.$artifact->id.'/download');
});

it('does not create a purported complete artifact when the attached source exceeds the full-document limit', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false]);
    $sourceText = str_repeat('Academic calendar event and organizer details. ', 900);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence('word', 'Incomplete output', 'Summary')]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Hãy thống kê toàn bộ sự kiện trong tài liệu và tạo file Word.',
        'documents' => [[
            'name' => 'oversized-calendar.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode($sourceText),
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('artifact_error', fn (string $error) => str_contains($error, 'full-processing limit'));

    expect(AiArtifact::query()->exists())->toBeFalse();
});

it('creates a file only when OpenAI selects the artifact tool and format', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence('word', '# Bilingual content', 'Bilingual file ready.')]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Hãy tạo ra file song ngữ, một dòng tiếng Việt, một dòng tiếng Hoa',
    ]);

    $response->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.docx'))
        ->assertJsonPath('reply', fn (string $reply) => str_contains($reply, '/ai-plus/artifacts/'));
});

it('replaces an incorrect model refusal when the requested file was created', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence('word', '# Actual document content', 'The Word file is ready.')]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Tạo file Word cho nội dung này']);

    $response->assertOk()
        ->assertJsonPath('reply', fn (string $reply) => str_starts_with($reply, 'The Word file is ready.'));
});

it('exports the previously prepared content when a user asks to export it as Word', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence(
        'word',
        "# Detailed trip plan prepared earlier\n\n## Budget\n\n- Hotel: **80 USD**\n\n| Item | Cost |\n| --- | ---: |\n| Hotel | 80 USD |",
        'The Word document is ready.',
    )]);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Trip plan',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => "# Detailed trip plan prepared earlier\n\n## Budget\n\n- Hotel: **80 USD**\n\n| Item | Cost |\n| --- | ---: |\n| Hotel | 80 USD |",
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'I cannot create the file. 📄 **File ready:** [old.docx](/ai-plus/artifacts/99/download)',
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Tạo file Word mới với nội dung kế hoạch đã lập ở trên',
    ])->assertOk();

    $artifact = AiArtifact::firstOrFail();
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue();
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($documentXml)
        ->toContain('Detailed trip plan prepared earlier')
        ->toContain('80 USD')
        ->toContain('<w:b w:val="1"/>')
        ->toContain('<w:tbl>');
});

it('creates Word exports with well-formed XML when text contains ampersands', function () {
    $artifact = app(ArtifactService::class)->generate(
        $this->user,
        null,
        'word',
        "# Lesson plan\n\nGoal & objectives\n\nData & assessment",
    );

    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue();
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    $xml = new DOMDocument;
    expect($documentXml)->toContain('Goal &amp; objectives')
        ->and(@$xml->loadXML($documentXml))->toBeTrue();
});

it('creates an opening Word file from a translated document with nested lists and a table', function () {
    $content = <<<'MARKDOWN'
# English lesson plan

## Learning objectives

1. Understand flood risks.
   - Identify affected communities.
   - Compare nature inspired solutions.
2. Design a flood resistant house.
   - Explain how a water lily floats.

| Activity | Goal & assessment |
| --- | --- |
| Discussion | Explain the concept & give examples |
MARKDOWN;

    $artifact = app(ArtifactService::class)->generate($this->user, null, 'word', $content);
    $archive = new ZipArchive;
    expect($archive->open(Storage::disk('ai-artifacts')->path($artifact->path)))->toBeTrue();
    $xml = new DOMDocument;
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect(@$xml->loadXML($documentXml))->toBeTrue()
        ->and($documentXml)->toContain('Identify affected communities')
        ->toContain('Goal &amp; assessment');
});

it('formats Markdown exports for Excel, PDF, and HTML', function () {
    $content = "# Budget summary\n\n- Hotel: **80 USD**\n\n| Item | Cost |\n| --- | ---: |\n| Hotel | 80 USD |";
    $service = app(ArtifactService::class);

    $excel = $service->generate($this->user, null, 'excel', $content);
    $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load(Storage::disk('ai-artifacts')->path($excel->path));
    $sheet = $workbook->getActiveSheet();
    expect($sheet->getCell('A1')->getValue())->toBe('Budget summary')
        ->and($sheet->getCell('A5')->getValue())->toBe('Item')
        ->and($sheet->getCell('B6')->getValue())->toBe('80 USD');

    $pdf = $service->generate($this->user, null, 'pdf', $content);
    expect(Storage::disk('ai-artifacts')->get($pdf->path))->toStartWith('%PDF');

    $html = $service->generate($this->user, null, 'html', $content);
    expect(Storage::disk('ai-artifacts')->get($html->path))
        ->toContain('<h1>Budget summary</h1>')
        ->toContain('<table>');
});

it('exports a conversation with user-selected document options', function () {
    config(['openai.api_key' => 'sk-test']);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Business trip',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Please prepare the full business trip record.',
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => '# Business trip budget\n\n| Item | Cost |\n| --- | ---: |\n| Hotel | 80 USD |',
    ]);

    $response = $this->postJson(route('ai-plus.conversations.export', $conversation), [
        'format' => 'excel',
        'filename' => 'Business Trip Budget',
        'language' => 'en',
        'template' => 'budget',
    ]);

    $response->assertOk()
        ->assertJsonPath('name', 'business-trip-budget.xlsx')
        ->assertJsonPath('url', fn (string $url) => str_contains($url, '/ai-plus/artifacts/'));
    $artifact = AiArtifact::firstOrFail();
    Storage::disk('ai-artifacts')->assertExists($artifact->path);
    expect(UsageLog::where('source', 'agent_workspace_export')->exists())->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        'Please prepare the full business trip record.',
    ) && str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        'Business trip budget',
    ));
});

it('lets an owner delete a generated file from recent files', function () {
    $artifact = app(ArtifactService::class)->generate($this->user, null, 'html', '# Temporary file');
    Storage::disk('ai-artifacts')->assertExists($artifact->path);

    $this->deleteJson(route('ai-plus.artifacts.destroy', $artifact))
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(AiArtifact::find($artifact->id))->toBeNull();
    Storage::disk('ai-artifacts')->assertMissing($artifact->path);
    expect(AdminAuditLog::where('event', 'ai_artifact.deleted')->exists())->toBeTrue();
});

it('does not let another user delete a generated file', function () {
    $artifact = app(ArtifactService::class)->generate($this->user, null, 'html', '# Private file');
    $other = User::factory()->create();

    $this->actingAs($other)
        ->deleteJson(route('ai-plus.artifacts.destroy', $artifact))
        ->assertForbidden();

    expect(AiArtifact::find($artifact->id))->not->toBeNull();
    Storage::disk('ai-artifacts')->assertExists($artifact->path);
});

it('creates an HTML artifact from the AI code block', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedArtifactResponseSequence('html', "```html\n<!doctype html><html><head><meta charset=\"utf-8\"><title>Updated</title></head><body><h1>Updated</h1></body></html>\n```", 'Updated page is ready.'),
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', ['message' => 'Hãy sửa file HTML và tạo file HTML'])
        ->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.html'));

    $artifact = AiArtifact::firstOrFail();
    expect(Storage::disk('ai-artifacts')->get($artifact->path))
        ->toContain('<!doctype html>')
        ->toContain('<h1>Updated</h1>');
});

it('uses the model-selected HTML format for a Vietnamese homepage file request', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedArtifactResponseSequence('html', "```html\n<!doctype html><html lang=\"vi\"><head><meta charset=\"utf-8\"><title>Apps</title></head><body><main><h1>Apps</h1><a href=\"#\">App 1</a></main></body></html>\n```", 'Trang HTML đã sẵn sàng.'),
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'viết 1 file html cho trang chủ có 10 thẻ dẫn đến các app interactives',
    ]);

    $response->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.html'));

    $artifact = AiArtifact::firstOrFail();
    expect(Storage::disk('ai-artifacts')->get($artifact->path))
        ->toContain('<!doctype html>')
        ->toContain('<a href="#">App 1</a>');
});

it('does not infer or retry file creation when the model does not call the artifact tool', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Mình có thể tạo file HTML nếu bạn muốn.']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 8],
        'model' => 'gpt-6-luna',
    ])]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'viết file HTML cho trang chủ có 10 thẻ app',
    ])->assertOk()->assertJsonPath('artifacts', []);

    expect(AiArtifact::count())->toBe(0)
        ->and($response->json('reply'))->toBe('Mình có thể tạo file HTML nếu bạn muốn.');
});

it('does not create a file when the model proposes one for a normal question', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(artifactToolCallResponse('word', '# Unrequested file content'))
        ->push(fileActionConfirmationResponse('A right triangle has one 90-degree angle.'))
        ->push(['choices' => [['message' => ['content' => 'Right triangle']]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2], 'model' => 'gpt-6-luna'])]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'What is a right triangle?',
    ])->assertOk()
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('reply', 'A right triangle has one 90-degree angle.');

    expect(AiArtifact::query()->exists())->toBeFalse();
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => ! isset($request->data()['tools'])
        && str_contains(json_encode($request->data()['messages'], JSON_UNESCAPED_UNICODE), 'AI+ FILE ACTION CONFIRMATION GATE'));
});

it('creates an email draft but never sends an email', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => Http::response(emailDraftToolCallResponse('Meeting notice', 'Please join the meeting tomorrow.', 'Draft saved.'))]);

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

it('does not turn an existing Quick Chat into an Agent chat from a stale agent id', function () {
    config(['openai.api_key' => 'sk-test']);
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Previous Agent',
        'system_prompt' => 'You help with school work.',
        'is_shared' => false,
    ]);
    $quickChat = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Quick Chat',
        'type' => Conversation::TYPE_CHAT,
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Continue this quick chat',
        'conversation_id' => $quickChat->id,
        // Simulates an outdated browser tab that still remembers a selected Agent.
        'agent_id' => $agent->id,
    ])->assertOk();

    expect($quickChat->fresh()->agent_id)->toBeNull();
});

it('moves a conversation between Quick Chat and an owned Agent without removing its history', function () {
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Writing Assistant',
        'system_prompt' => 'Help write school documents.',
        'is_shared' => false,
    ]);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Staff note',
        'type' => Conversation::TYPE_CHAT,
    ]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Existing message']);

    $this->patchJson(route('ai-plus.conversations.move', $conversation), ['agent_id' => $agent->id])
        ->assertOk()
        ->assertJsonPath('agent_id', $agent->id);
    expect($conversation->fresh()->agent_id)->toBe($agent->id)
        ->and($conversation->messages()->count())->toBe(1);

    $this->patchJson(route('ai-plus.conversations.move', $conversation), ['agent_id' => null])
        ->assertOk()
        ->assertJsonPath('agent_id', null);
    expect($conversation->fresh()->agent_id)->toBeNull()
        ->and($conversation->messages()->count())->toBe(1);
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

it('asks the model to help with professional profiles instead of refusing on work-use scope alone', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Rewrite my LinkedIn work experience to appeal to recruiters.',
    ])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $system = (string) ($request->data()['messages'][0]['content'] ?? '');

        return str_contains($system, "Writing a staff member's CV, LinkedIn profile")
            && str_contains($system, 'begin with one brief, visible reminder')
            && str_contains($system, "then complete the user's request normally")
            && ! str_contains($system, 'ask the user to provide a legitimate school-work context');
    });
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

it('labels an Agent reply when the question is outside its indexed Knowledge', function () {
    config(['openai.api_key' => 'sk-test']);
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Leave policy Agent',
        'system_prompt' => 'Answer staff questions.',
        'knowledge' => json_encode([['path' => 'placeholder.txt', 'original_name' => 'leave-policy.txt']]),
        'is_shared' => false,
    ]);
    KnowledgeChunk::create([
        'agent_id' => $agent->id,
        'source_file' => 'leave-policy.txt',
        'chunk_index' => 0,
        'content' => 'Nhân viên cần nộp đơn nghỉ phép trước ba ngày làm việc.',
        'embedding' => [],
    ]);
    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'agent_id' => $agent->id,
        'title' => 'Agent chat',
        'type' => Conversation::TYPE_CHAT,
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Giải giúp tôi bài toán 2 + 2.',
        'conversation_id' => $conversation->id,
    ])->assertOk()
        ->assertJsonPath('reply', 'ℹ️ **Knowledge notice:** This question appears to be outside this Agent’s Knowledge. The response below is based on the AI model’s general knowledge, not the Agent’s uploaded materials.'."\n\n".'Phản hồi từ AI');

    expect($conversation->messages()->where('role', 'assistant')->sole()->content)
        ->toStartWith('ℹ️ **Knowledge notice:**');
});

it('does not label an Agent reply when indexed Knowledge is relevant', function () {
    config(['openai.api_key' => 'sk-test']);
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Leave policy Agent',
        'knowledge' => json_encode([['path' => 'placeholder.txt', 'original_name' => 'leave-policy.txt']]),
        'is_shared' => false,
    ]);
    KnowledgeChunk::create([
        'agent_id' => $agent->id,
        'source_file' => 'leave-policy.txt',
        'chunk_index' => 0,
        'content' => 'Nhân viên cần nộp đơn nghỉ phép trước ba ngày làm việc.',
        'embedding' => [],
    ]);
    $conversation = Conversation::create(['user_id' => $this->user->id, 'agent_id' => $agent->id, 'title' => 'Agent chat']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Quy trình nộp đơn nghỉ phép như thế nào?',
        'conversation_id' => $conversation->id,
    ])->assertOk()
        ->assertJsonPath('reply', 'Phản hồi từ AI');
});

it('uses the Agent system prompt as an additional scope signal', function () {
    config(['openai.api_key' => 'sk-test']);
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Business Trip Budget Agent',
        'system_prompt' => 'You are a business trip budget specialist. Help staff calculate airfare, hotel, meals, and travel allowances.',
        'is_shared' => false,
    ]);
    $conversation = Conversation::create(['user_id' => $this->user->id, 'agent_id' => $agent->id, 'title' => 'Budget chat']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Giải giúp tôi bài toán 2 + 2.',
        'conversation_id' => $conversation->id,
    ])->assertOk()
        ->assertJsonPath('reply', 'ℹ️ **Knowledge notice:** This question appears to be outside this Agent’s Knowledge. The response below is based on the AI model’s general knowledge, not the Agent’s uploaded materials.'."\n\n".'Phản hồi từ AI');

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'How should I calculate hotel allowance for a business trip?',
        'conversation_id' => $conversation->id,
    ])->assertOk()
        ->assertJsonPath('reply', 'Phản hồi từ AI');
});

it('does not mistake overlapping generic prompt words for Agent scope', function () {
    $agent = Agent::create([
        'user_id' => $this->user->id,
        'title' => 'Budget Agent',
        'system_prompt' => 'Prepare travel budgets, payment proposals, quantities, and equipment costs for school trips.',
        'is_shared' => false,
    ]);

    expect(app(KnowledgeService::class)->promptScopeMatches(
        $agent->system_prompt,
        'Thiết kế một bài toán lớp 9 về góc lượng giác.',
    ))->toBeFalse()
        ->and(app(KnowledgeService::class)->promptScopeMatches(
            $agent->system_prompt,
            'Prepare a hotel budget and travel allowance proposal.',
        ))->toBeTrue();
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

it('adds relevant School Knowledge Base context to a regular workspace chat', function () {
    config(['openai.api_key' => 'sk-test', 'openai.rag_embeddings_enabled' => false]);
    $source = SchoolKnowledgeSource::create([
        'type' => 'upload',
        'title' => 'School handbook',
        'status' => 'ready',
    ]);
    SchoolKnowledgeChunk::create([
        'school_knowledge_source_id' => $source->id,
        'chunk_index' => 0,
        'content' => 'SCHOOLHANDBOOK2026 explains the official staff leave procedure.',
        'embedding' => [],
    ]);
    $conv = Conversation::create(['user_id' => $this->user->id, 'title' => 'School Knowledge']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'What does SCHOOLHANDBOOK2026 say?',
        'conversation_id' => $conv->id,
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_contains(
        (string) data_get($request->data(), 'messages.0.content'),
        '=== SCHOOL KNOWLEDGE BASE (retrieved reference material) ===',
    ));
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

    $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII=';

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

    $storedMessage = Message::where('role', 'user')->latest('id')->firstOrFail();
    expect($storedMessage->content)
        ->toContain('lesson-plan.txt')
        ->not->toContain('Nội dung kế hoạch bài học')
        ->and($storedMessage->internal_context)
        ->toContain('không yêu cầu user tải lại tệp gốc')
        ->toContain('Nội dung kế hoạch bài học');

    Http::assertSent(function (Request $request) {
        return str_contains(
            json_encode($request->data(), JSON_UNESCAPED_UNICODE),
            'Nội dung kế hoạch bài học',
        );
    });
});

it('reads a document whose browser only provides a generic MIME type', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Read this note',
        'documents' => [[
            'name' => 'school-note.txt',
            'data_url' => 'data:application/octet-stream;base64,'.base64_encode('School schedule details'),
        ]],
    ])->assertOk();

    expect(Message::where('role', 'user')->sole()->internal_context)
        ->toContain('School schedule details');
});

it('rejects a damaged Word attachment before creating a chat or opening a stream', function () {
    $payload = [
        'message' => 'Check this document',
        'documents' => [[
            'name' => 'exam.docx',
            'data_url' => 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,'.base64_encode('not a DOCX package'),
        ]],
    ];

    foreach (['/ai-plus/agent-workspace/send', '/ai-plus/agent-workspace/send-stream'] as $url) {
        $this->postJson($url, $payload)
            ->assertStatus(422)
            ->assertJsonPath('error', fn (string $error) => str_contains($error, 'could not be read'));
    }

    expect(Conversation::count())->toBe(0);
});

it('reads a valid Word attachment in the same request path', function () {
    config(['openai.api_key' => 'sk-test']);

    $path = tempnam(sys_get_temp_dir(), 'valid-chat-docx-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Exam content after the file title.</w:t></w:r></w:p></w:body></w:document>');
    $archive->close();

    try {
        $this->postJson('/ai-plus/agent-workspace/send', [
            'message' => 'Check this exam',
            'documents' => [[
                'name' => 'exam.docx',
                'data_url' => 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,'.base64_encode(file_get_contents($path)),
            ]],
        ])->assertOk();

        expect(Message::where('role', 'user')->sole()->internal_context)
            ->toContain('Exam content after the file title.');
    } finally {
        @unlink($path);
    }
});

it('reads a real PDF attachment before sending the chat request', function () {
    config(['openai.api_key' => 'sk-test']);

    $pdf = new \Dompdf\Dompdf;
    $pdf->loadHtml('<html><body><p>Exam instructions for teachers.</p></body></html>');
    $pdf->render();

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Check this PDF',
        'documents' => [[
            'name' => 'exam.pdf',
            'data_url' => 'data:application/pdf;base64,'.base64_encode($pdf->output()),
        ]],
    ])->assertOk();

    expect(Message::where('role', 'user')->sole()->internal_context)
        ->toContain('Exam instructions for teachers.');
});

it('reads the requested later PDF page instead of using opening-page excerpts', function () {
    config(['openai.api_key' => 'sk-test']);

    $pages = [];
    for ($page = 1; $page <= 116; $page++) {
        $content = $page === 1 ? 'BOOK_OPENING_PAGE_ONLY' : 'Page content '.$page;
        if ($page === 116) {
            $content = 'PAGE_116_REQUESTED_CONTENT';
        }
        $pages[] = '<div style="page-break-after: always">'.$content.'</div>';
    }

    $pdf = new \Dompdf\Dompdf;
    $pdf->loadHtml('<html><body>'.implode('', $pages).'</body></html>');
    $pdf->render();

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Đọc trang 116 trong file PDF này',
        'documents' => [[
            'name' => 'textbook.pdf',
            'data_url' => 'data:application/pdf;base64,'.base64_encode($pdf->output()),
        ]],
    ])->assertOk();

    $context = Message::where('role', 'user')->sole()->internal_context;
    expect($context)
        ->toContain('trang PDF 116')
        ->toContain('PAGE_116_REQUESTED_CONTENT')
        ->not->toContain('BOOK_OPENING_PAGE_ONLY');
});

it('renders the requested PDF page when that page has no extractable text', function () {
    config(['openai.api_key' => 'sk-test']);

    $pdf = '%PDF-1.4 test fixture';
    $knowledgeService = \Mockery::mock(KnowledgeService::class);
    $knowledgeService->shouldReceive('extractTextFromBinary')
        ->once()
        ->with($pdf, 'pdf')
        ->andReturn('BOOK_OPENING_PAGE_ONLY');
    $knowledgeService->shouldReceive('extractPdfPagesText')
        ->once()
        ->with($pdf, 116, 116)
        ->andReturn('');
    $knowledgeService->shouldReceive('renderScannedPdfPages')
        ->once()
        ->with($pdf, 116, 116)
        ->andReturn(['data:image/jpeg;base64,VEFSR0VUX1BBR0U=']);
    app()->instance(KnowledgeService::class, $knowledgeService);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Đọc trang 116 trong file PDF này',
        'documents' => [[
            'name' => 'scanned-textbook.pdf',
            'data_url' => 'data:application/pdf;base64,'.base64_encode($pdf),
        ]],
    ])->assertOk();

    $context = Message::where('role', 'user')->sole()->internal_context;
    expect($context)
        ->toContain('trang 116')
        ->not->toContain('BOOK_OPENING_PAGE_ONLY');

    Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request->data()), 'image_url'));
});

it('reads a real Excel attachment before sending the chat request', function () {
    config(['openai.api_key' => 'sk-test']);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Exam schedule for teachers');
    $path = tempnam(sys_get_temp_dir(), 'valid-chat-xlsx-');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

    try {
        $this->postJson('/ai-plus/agent-workspace/send', [
            'message' => 'Check this spreadsheet',
            'documents' => [[
                'name' => 'exam.xlsx',
                'data_url' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,'.base64_encode(file_get_contents($path)),
            ]],
        ])->assertOk();

        expect(Message::where('role', 'user')->sole()->internal_context)
            ->toContain('Exam schedule for teachers');
    } finally {
        @unlink($path);
        $spreadsheet->disconnectWorksheets();
    }
});

it('keeps Word, Excel, PDF, and an image together in one chat turn', function () {
    config(['openai.api_key' => 'sk-test']);

    $wordPath = tempnam(sys_get_temp_dir(), 'mixed-chat-docx-');
    $word = new ZipArchive;
    $word->open($wordPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $word->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Word lesson content</w:t></w:r></w:p></w:body></w:document>');
    $word->close();

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Excel scores content');
    $excelPath = tempnam(sys_get_temp_dir(), 'mixed-chat-xlsx-');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($excelPath);

    $pdf = new \Dompdf\Dompdf;
    $pdf->loadHtml('<html><body><p>PDF exam content</p></body></html>');
    $pdf->render();

    try {
        $response = $this->postJson('/ai-plus/agent-workspace/send', [
            'message' => 'Compare every attachment',
            'images' => ['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII='],
            'documents' => [
                ['name' => 'lesson.docx', 'data_url' => 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,'.base64_encode(file_get_contents($wordPath))],
                ['name' => 'scores.xlsx', 'data_url' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,'.base64_encode(file_get_contents($excelPath))],
                ['name' => 'exam.pdf', 'data_url' => 'data:application/pdf;base64,'.base64_encode($pdf->output())],
            ],
        ]);

        $response->assertOk();
        $context = Message::where('role', 'user')->sole()->internal_context;
        expect($context)
            ->toContain('Word lesson content')
            ->toContain('Excel scores content')
            ->toContain('PDF exam content');

        Http::assertSent(fn (Request $request): bool => str_contains(json_encode($request->data()), 'image_url'));
    } finally {
        @unlink($wordPath);
        @unlink($excelPath);
        $spreadsheet->disconnectWorksheets();
    }
});

it('keeps a code-only HTML attachment available for an editing request', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Edit the HTML code in this file',
        'documents' => [[
            'name' => 'app.html',
            'data_url' => 'data:text/html;base64,'.base64_encode('<script>console.log("school app")</script>'),
        ]],
    ])->assertOk();

    expect(Message::where('role', 'user')->sole()->internal_context)
        ->toContain('console.log');
});

it('reads a Python source attachment and returns an edited file with the same extension when requested', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedCodePatchResponseSequence(codePatchToolCallResponse(
            'call-code-edit',
            'Fixed the broken output.',
            [['find' => "print('broken')", 'replace' => "print('fixed')"]],
        )),
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Please fix this code and return the updated file.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('broken')"),
        ]],
    ])->assertOk()
        ->assertJsonPath('artifacts.0.name', 'school-tool.py');

    $artifact = AiArtifact::firstOrFail();
    expect($artifact->mime_type)->toBe('text/plain; charset=UTF-8')
        ->and(Storage::disk('ai-artifacts')->get($artifact->path))->toBe("print('fixed')")
        ->and($response->json('reply'))->toContain('Fixed the broken output.');

    Http::assertSent(fn (Request $request): bool => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        'print(\'broken\')',
    ) && $request->data()['reasoning_effort'] === 'none'
        && $request->data()['tool_choice'] === 'auto'
        && $request->data()['tools'][0]['function']['name'] === 'apply_code_patch');
});

it('applies a model-selected code patch through the chat streaming endpoint', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    $toolArguments = json_encode([
        'summary' => 'Updated the greeting.',
        'edits' => [['find' => "print('before')", 'replace' => "print('after')"]],
    ]);
    $events = [
        ['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call-stream-code-edit',
            'type' => 'function',
            'function' => ['name' => 'apply_code_patch', 'arguments' => $toolArguments],
        ]]], 'finish_reason' => null]], 'model' => 'gpt-6-luna'],
        ['choices' => [['delta' => [], 'finish_reason' => 'tool_calls']], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 10], 'model' => 'gpt-6-luna'],
    ];
    $streamBody = collect($events)
        ->map(fn (array $event): string => 'data: '.json_encode($event)."\n\n")
        ->push("data: [DONE]\n\n")
        ->implode('');
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push($streamBody, 200, ['Content-Type' => 'text/event-stream'])
        ->push(fileActionConfirmationResponse())]);

    $response = $this->post('/ai-plus/agent-workspace/send-stream', [
        'message' => 'Please update the greeting and return the file.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('before')\n"),
        ]],
    ])->assertOk();

    $response->baseResponse->getCallback()();
    $artifact = AiArtifact::firstOrFail();
    expect(Storage::disk('ai-artifacts')->get($artifact->path))->toBe("print('after')\n")
        ->and(Message::where('role', 'assistant')->sole()->content)->toContain('Updated the greeting.');
});

it('does not create a code artifact when a source attachment is only being reviewed', function () {
    config(['openai.api_key' => 'sk-test']);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Review this Python file and explain what it does.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('hello')"),
        ]],
    ])->assertOk()->assertJsonPath('artifacts', []);

    expect(AiArtifact::count())->toBe(0)
        ->and(Message::where('role', 'user')->sole()->internal_context)->toContain('Source code file: school_tool.py');
});

it('does not create a code artifact when the model proposes an edit for an explanation request', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => Http::sequence()
        ->push(codePatchToolCallResponse('call-unrequested-code-edit', 'Updated the file.', [['find' => "print('hello')", 'replace' => "print('goodbye')"]]))
        ->push(fileActionConfirmationResponse('This Python file prints “hello” to the console.'))
        ->push(['choices' => [['message' => ['content' => 'Explain Python file']]], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 2], 'model' => 'gpt-6-luna'])]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Explain what this Python file does.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('hello')"),
        ]],
    ])->assertOk()
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('reply', 'This Python file prints “hello” to the console.');

    expect(AiArtifact::query()->exists())->toBeFalse();
    Http::assertSentCount(3);
});

it('treats a question asking for code-edit suggestions as advice instead of an edit command', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'The script could validate the input path and report skipped document sections.']]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 12],
            'model' => 'gpt-6-luna',
        ]),
    ]);

    $upload = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Explain what this Python file does.',
        'documents' => [[
            'name' => 'mask_pii.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('hello')"),
        ]],
    ])->assertOk();

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $upload->json('conversation_id'),
        'message' => 'Bạn có đề xuất sửa gì không?',
    ])->assertOk()
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('reply', 'The script could validate the input path and report skipped document sections.');

    expect(AiArtifact::count())->toBe(0);
    Http::assertSent(fn (Request $request): bool => str_contains(
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
        'apply_code_patch',
    ) && $request->data()['reasoning_effort'] === 'none');
});

it('retries a code patch once when its exact source match is invalid', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedCodePatchResponseSequence(
            codePatchToolCallResponse('call-bad-patch', 'Updated the greeting.', [['find' => "print('missing')", 'replace' => "print('updated')"]]),
            codePatchToolCallResponse('call-retry-patch', 'Updated the greeting.', [['find' => "print('before')", 'replace' => "print('after')"]], 24, 9),
        ),
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Vậy bạn sửa theo đề xuất và xuất file mới có các phần đã sửa giúp mình.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('before')\n"),
        ]],
    ])->assertOk()
        ->assertJsonPath('artifacts.0.name', 'school-tool.py');

    expect($response->json('reply'))->toContain('Updated the greeting.')
        ->and($response->json('reply'))->toContain('File ready:');

    $artifact = AiArtifact::firstOrFail();
    expect(Storage::disk('ai-artifacts')->get($artifact->path))->toBe("print('after')\n")
        ->and(Message::where('role', 'assistant')->sole()->prompt_tokens)->toBe(49)
        ->and(Message::where('role', 'assistant')->sole()->completion_tokens)->toBe(19);

    Http::assertSentCount(3);
});

it('can patch a previously uploaded source file in a follow-up turn', function () {
    config(['openai.api_key' => 'sk-test']);
    $upload = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Please review this file.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print('before')\n"),
        ]],
    ])->assertOk();
    $conversationId = $upload->json('conversation_id');
    $uploadedMessage = Message::where('role', 'user')->sole();
    Storage::disk('chat-attachments')->delete($conversationId.'/source-code/'.$uploadedMessage->id.'-0.py');

    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedCodePatchResponseSequence(codePatchToolCallResponse(
            'call-follow-up',
            'Updated the greeting.',
            [['find' => "print('before')", 'replace' => "print('after')"]],
        )),
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversationId,
        'message' => 'Fix the greeting in the code and return the file.',
    ])->assertOk()->assertJsonPath('artifacts.0.name', 'school-tool.py');

    $artifact = AiArtifact::firstOrFail();
    expect(Storage::disk('ai-artifacts')->get($artifact->path))->toBe("print('after')\n")
        ->and($response->json('reply'))->toContain('Updated the greeting.');
});

it('applies a small patch to a long file and preserves its sections outside model context', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedCodePatchResponseSequence(codePatchToolCallResponse(
            'call-long-source',
            'Updated the marker.',
            [['find' => '# update-me', 'replace' => '# updated']],
        )),
    ]);
    $source = "# update-me\n".str_repeat("print(\"line\")\n", 1800)."# preserve-this-tail\n";

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Fix this code and export the file again.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode($source),
        ]],
    ])->assertOk()->assertJsonPath('artifacts.0.name', 'school-tool.py');

    $sourcePath = Conversation::firstOrFail()->id.'/source-code/'.Message::where('role', 'user')->sole()->id.'-0.py';
    expect(Storage::disk('chat-attachments')->get($sourcePath))->toBe($source);
    $artifact = AiArtifact::firstOrFail();
    $patchedSource = Storage::disk('ai-artifacts')->get($artifact->path);
    expect($patchedSource)->toStartWith("# updated\n")
        ->and(substr($patchedSource, -21))->toBe("# preserve-this-tail\n")
        ->and(strlen($patchedSource))->toBe(strlen($source) - strlen('# update-me') + strlen('# updated'))
        ->and(Message::where('role', 'user')->sole()->internal_context)
        ->toContain('[SOURCE CODE TRUNCATED: only the first 20000 characters are visible to you.');
    expect(Storage::disk('chat-attachments')->allFiles())->toContain(
        Conversation::firstOrFail()->id.'/source-code/'.Message::where('role', 'user')->sole()->id.'-0.py',
    );
});

it('does not create a code artifact when a proposed patch matches multiple locations', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake([
        'https://api.openai.com/*' => confirmedCodePatchResponseSequence(
            codePatchToolCallResponse('call-duplicate-first', 'Updated repeated code.', [['find' => 'print("same")', 'replace' => 'print("changed")']]),
            codePatchToolCallResponse('call-duplicate-retry', 'Updated repeated code.', [['find' => 'print("same")', 'replace' => 'print("changed")']]),
        ),
    ]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Fix this Python code.',
        'documents' => [[
            'name' => 'school_tool.py',
            'data_url' => 'data:text/x-python;base64,'.base64_encode("print(\"same\")\nprint(\"same\")"),
        ]],
    ])->assertOk()->assertJsonPath('artifacts', []);

    expect(AiArtifact::count())->toBe(0)
        ->and($response->json('reply'))->toContain('exactly one place');
});

it('rejects a file named PDF that is not actually a PDF', function () {
    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Check this PDF',
        'documents' => [[
            'name' => 'exam.pdf',
            'data_url' => 'data:application/pdf;base64,'.base64_encode('not a PDF'),
        ]],
    ])->assertStatus(422)->assertJsonPath('error', fn (string $error) => str_contains($error, 'exam.pdf'));

    expect(Conversation::count())->toBe(0);
});

it('rejects empty and malformed attachment data before creating a chat', function () {
    foreach (['', '===='] as $badBase64) {
        $this->postJson('/ai-plus/agent-workspace/send', [
            'message' => 'Read this file',
            'documents' => [[
                'name' => 'notes.txt',
                'data_url' => 'data:text/plain;base64,'.$badBase64,
            ]],
        ])->assertStatus(422);
    }

    expect(Conversation::count())->toBe(0);
});

it('rejects a corrupt image or an image with a mismatched declared type', function () {
    foreach ([
        'data:image/png;base64,'.base64_encode('not an image'),
        'data:image/jpeg;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII=',
    ] as $dataUrl) {
        $this->postJson('/ai-plus/agent-workspace/send', [
            'message' => 'Describe the image',
            'images' => [$dataUrl],
        ])->assertStatus(422)->assertJsonPath('error', fn (string $error) => str_contains($error, 'image is invalid'));
    }

    expect(Conversation::count())->toBe(0);
});

it('accepts a real image in each supported chat format', function (string $mime, string $encoder) {
    config(['openai.api_key' => 'sk-test']);

    $image = imagecreatetruecolor(2, 2);
    ob_start();
    $encoder($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Describe this image',
        'images' => ['data:'.$mime.';base64,'.base64_encode($bytes)],
    ])->assertOk();
})->with([
    ['image/png', 'imagepng'],
    ['image/jpeg', 'imagejpeg'],
    ['image/gif', 'imagegif'],
    ['image/webp', 'imagewebp'],
]);

it('keeps the latest uploaded document available beyond the normal chat-history limit', function () {
    config(['openai.api_key' => 'sk-test']);

    $conversation = Conversation::create([
        'user_id' => $this->user->id,
        'title' => 'Training document',
        'type' => Conversation::TYPE_CHAT,
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => "Dịch tài liệu\n\n📎 Tệp đính kèm: training.docx",
        'internal_context' => '📎 Tài liệu đính kèm đã nhận: training.docx' . "\n" . str_repeat('Nội dung trước. ', 280) . "\nGoal &amp; Use Case\nCác mục quan trọng nằm sau tiêu đề này.",
    ]);
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Đã dịch tài liệu.',
    ]);

    $this->postJson('/ai-plus/agent-workspace/send', [
        'conversation_id' => $conversation->id,
        'message' => 'Bạn xem lại nội dung tài liệu vừa gửi',
    ])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $messages = $request->data()['messages'] ?? [];

        return collect($messages)->contains(fn (array $message): bool => is_string($message['content'] ?? null)
            && str_contains($message['content'], 'Goal & Use Case')
            && str_contains($message['content'], 'Các mục quan trọng nằm sau tiêu đề này.'));
    });
});

it('exports an attached document translation as Word without putting source or translation text in the reply bubble', function () {
    config(['openai.api_key' => 'sk-test']);
    Http::swap(new Factory);
    Http::fake(['https://api.openai.com/*' => confirmedArtifactResponseSequence(
        'word',
        '# Translated lesson' . "\n\n" . 'Goal & objectives',
        'Đã dịch tài liệu và tạo file Word để tải xuống.',
    )]);

    $response = $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Dịch file này sang tiếng Anh',
        'documents' => [[
            'name' => 'lesson-plan.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode('Nội dung bài học tiếng Việt'),
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('artifacts.0.name', fn (string $name) => str_ends_with($name, '.docx'))
        ->assertJsonPath('reply', fn (string $reply) => str_starts_with($reply, 'Đã dịch tài liệu'))
        ->assertJsonPath('reply', fn (string $reply) => ! str_contains($reply, 'Translated lesson'));

    $userMessage = Message::where('role', 'user')->sole();
    expect($userMessage->content)->toContain('lesson-plan.txt')
        ->not->toContain('Nội dung bài học tiếng Việt')
        ->and($userMessage->internal_context)->toContain('Nội dung bài học tiếng Việt');
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
        json_encode($request->data(), JSON_UNESCAPED_UNICODE),
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
        'images' => ['data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9t8AAAAASUVORK5CYII='],
        'documents' => $documents,
    ])->assertOk();

    Http::assertSent(function (Request $request) {
        $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return str_contains($payload, 'Đính kèm 1 hình ảnh')
            && str_contains($payload, 'word content')
            && str_contains($payload, 'excel content')
            && str_contains($payload, 'pdf content');
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

it('blocks a prompt containing ten seven-digit student identifiers', function () {
    $identifiers = implode(', ', range(1_000_001, 1_000_010));

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Hãy phân tích danh sách sau: '.$identifiers,
    ])->assertStatus(422)
        ->assertJsonPath('blocked', true)
        ->assertJsonPath('warning', fn (string $warning) => str_contains($warning, '10 mã số học sinh'));

    expect(Conversation::count())->toBe(0);
});

it('blocks a text attachment containing a student identifier with student-record labels', function () {
    $document = "Họ tên,MSSV,Lớp,Điểm\nNguyễn A,1234567,10A1,9";

    $this->postJson('/ai-plus/agent-workspace/send', [
        'message' => 'Đọc tài liệu đính kèm',
        'documents' => [[
            'name' => 'student-list.txt',
            'data_url' => 'data:text/plain;base64,'.base64_encode($document),
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('blocked', true)
        ->assertJsonPath('warning', fn (string $warning) => str_contains($warning, 'dữ liệu học sinh'));

    expect(Conversation::count())->toBe(0);
});
