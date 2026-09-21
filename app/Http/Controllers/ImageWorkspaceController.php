<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\User;
use App\Services\TokenQuotaService;
use App\Services\ImageGenerationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImageWorkspaceController extends Controller
{
    /** Display the dedicated, prompt-only image generation workspace. */
    public function index(Request $request, TokenQuotaService $tokenQuotaService): View
    {
        abort_unless(AppSetting::boolean('ai_plus_image_generation_enabled'), 404);

        /** @var User $user */
        $user = $request->user();
        $conversationId = $request->integer('conversation_id') ?: null;
        $conversation = $conversationId
            ? $user->conversations()->where('type', Conversation::TYPE_IMAGE)->find($conversationId)
            : null;

        $imageConversations = $user->conversations()
            ->where('type', Conversation::TYPE_IMAGE)
            ->latest('updated_at')
            ->get(['id', 'title', 'updated_at']);

        $latestImageId = $conversation?->messages()->where('role', 'assistant')->max('id');
        $initialImages = $conversation
            ? $conversation->messages()->where('role', 'assistant')->orderBy('id')->get()
                ->map(fn ($message) => [
                    'content' => $this->secureAttachmentUrls($message->content),
                    'prompt' => $this->promptForImage($conversation, $message->id),
                    'message_id' => $message->id,
                    'download_url' => route('ai-plus.agent-workspace.images.download', $message),
                    'is_latest' => $message->id === $latestImageId,
                ])->values()
            : collect();

        return view('ai-plus.agent-workspace.images.index', [
            'imageConversations' => $imageConversations,
            'initialImages' => $initialImages,
            'activeConversationId' => $conversation?->id,
            'activeConversationTitle' => $conversation?->title,
            'tokenQuota' => $tokenQuotaService->summary($user),
            'userName' => $user->name,
            'userInitials' => $user->initials,
            'imageModels' => $this->availableModels(),
            'defaultImageModel' => $this->defaultImageModel(),
        ]);
    }

    /** @return array<string, array{label: string, description: string, supports_edits: bool}> */
    private function availableModels(): array
    {
        $models = ImageGenerationService::modelOptions();
        $enabled = [
            ImageGenerationService::MODEL_FLARE => AppSetting::boolean('ai_plus_image_flare_enabled', true),
            ImageGenerationService::MODEL_SUNBURST => AppSetting::boolean('ai_plus_image_sunburst_enabled', true),
        ];

        return array_filter($models, fn (string $model) => $enabled[$model], ARRAY_FILTER_USE_KEY);
    }

    private function defaultImageModel(): string
    {
        $model = AppSetting::query()->where('key', 'ai_plus_image_default_model')->value('value')
            ?: ImageGenerationService::MODEL_FLARE;

        return array_key_exists($model, $this->availableModels())
            ? $model
            : array_key_first($this->availableModels());
    }

    private function secureAttachmentUrls(string $content): string
    {
        return preg_replace_callback(
            '#/storage/chat-attachments/(\d+)/([A-Za-z0-9_.-]+)#',
            fn (array $matches): string => route('ai-plus.agent-workspace.attachments.show', [
                'conversation' => $matches[1],
                'filename' => $matches[2],
            ], false),
            $content,
        ) ?? $content;
    }

    private function promptForImage(Conversation $conversation, int $messageId): string
    {
        $content = (string) $conversation->messages()->where('role', 'user')->where('id', '<', $messageId)->latest('id')->value('content');

        return preg_replace('/^(🎨 Generate image|🖼️ Edit image):\s*/u', '', $content) ?? $content;
    }
}
