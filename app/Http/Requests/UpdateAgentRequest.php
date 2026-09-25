<?php

namespace App\Http\Requests;

use App\Services\KnowledgeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
            'sharing_access' => [Rule::requiredIf(fn (): bool => $this->boolean('is_shared')), 'nullable', 'in:use_only,copy'],
            'knowledge_remove' => 'nullable|array',
            'knowledge_remove.*' => 'string',
            ...KnowledgeService::validationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'knowledge.max' => 'Mỗi Agent chỉ được tối đa '.KnowledgeService::MAX_AGENT_FILES.' file Knowledge.',
            'avatar.max' => 'Agent avatar must be 2 MB or smaller.',
            'avatar.mimes' => 'Agent avatar must be a JPG, PNG, or WebP image.',
            'knowledge.*.max' => 'Mỗi file Knowledge tối đa 15 MB.',
            'knowledge.*.mimes' => 'Chỉ chấp nhận file: '.implode(', ', KnowledgeService::ALLOWED_EXTENSIONS),
        ];
    }
}
