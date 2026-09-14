<?php

namespace App\Http\Requests;

use App\Services\KnowledgeService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
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
            'is_shared' => 'nullable|boolean',
            ...KnowledgeService::validationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'knowledge.*.max' => 'Mỗi file knowledge tối đa 5MB.',
            'knowledge.*.mimes' => 'Chỉ chấp nhận file: '.implode(', ', KnowledgeService::ALLOWED_EXTENSIONS),
        ];
    }
}
