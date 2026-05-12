<?php

declare(strict_types=1);

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class AgentRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'task' => 'required|string|max:2000',
            'conversation_id' => 'nullable|string',
            'max_steps' => 'nullable|integer|min:1|max:20',
        ];
    }
}
