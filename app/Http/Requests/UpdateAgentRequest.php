<?php

namespace App\Http\Requests;

use App\Services\KnowledgeService;
use App\Support\AgentCategories;
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
            ...AgentCategories::rules($this->input('category_group'), required: false),
            'system_prompt' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
            'sharing_scopes' => ['sometimes', 'array', 'min:1', 'max:3'],
            'sharing_scopes.*' => ['required', 'string', 'distinct', 'in:school,department,users'],
            'sharing_audience_configured' => ['sometimes', 'boolean'],
            'sharing_scope' => ['sometimes', 'required', 'in:school,department,users'],
            'sharing_recipients_configured' => ['sometimes', 'boolean'],
            'sharing_user_ids' => ['sometimes', 'array', 'max:100'],
            'sharing_user_ids.*' => ['required', 'integer', 'distinct'],
            'sharing_access' => [Rule::requiredIf(fn (): bool => $this->boolean('is_shared')), 'nullable', 'in:use_only,copy'],
            'knowledge_remove' => 'nullable|array',
            'knowledge_remove.*' => 'string',
            'connections' => ['sometimes', 'array'],
            'connections_configured' => ['sometimes', 'boolean'],
            'connections.*' => ['array:agent_id,role'],
            'connections.*.agent_id' => ['required', 'integer', 'distinct'],
            'connections.*.role' => ['nullable', 'string', 'max:500'],
            ...KnowledgeService::validationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.max' => 'Agent avatar must be 2 MB or smaller.',
            'avatar.mimes' => 'Agent avatar must be a JPG, PNG, or WebP image.',
            'knowledge.*.mimes' => 'Chỉ chấp nhận file: '.implode(', ', KnowledgeService::ALLOWED_EXTENSIONS),
        ];
    }
}
