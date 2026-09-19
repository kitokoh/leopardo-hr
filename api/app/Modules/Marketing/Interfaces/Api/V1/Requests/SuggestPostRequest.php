<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suggestion IA de contenu de post — Issue #7753.
 *
 * Le brief est du texte libre utilisateur : il est transmis au LLM comme
 * donnée (jamais comme instruction système) et la suggestion retournée
 * n'est jamais publiée automatiquement.
 */
class SuggestPostRequest extends FormRequest
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
            'brief' => ['required', 'string', 'min:3', 'max:2000'],
            'platforms' => ['nullable', 'array', 'max:10'],
            'platforms.*' => ['string', 'max:40'],
            'tone' => ['nullable', 'string', 'max:60'],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }
}
