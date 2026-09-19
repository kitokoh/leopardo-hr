<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suggestion IA de réponse à un commentaire — Issue #7754.
 *
 * Le commentaire source est une donnée non fiable (contenu tiers) : il est
 * transmis au LLM comme donnée utilisateur, jamais comme instruction, et la
 * suggestion produite doit être validée par un humain avant envoi.
 */
class SuggestReplyRequest extends FormRequest
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
            'comment' => ['required', 'string', 'min:1', 'max:4000'],
            'platform' => ['nullable', 'string', 'max:40'],
            'tone' => ['nullable', 'string', 'max:60'],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }
}
