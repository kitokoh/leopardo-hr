<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Chat IA plateforme (admin).
 */
class PlatformAiChatRequest extends FormRequest
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
        return ['message' => ['required', 'string', 'max:4000'], 'conversation_id' => ['nullable', 'integer']];
    }
}
