<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réponse à un commentaire d'un post publié — Issue #7754.
 */
class ReplyToCommentsRequest extends FormRequest
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
            'comment' => ['required', 'string', 'min:1', 'max:2000'],
            'platforms' => ['nullable', 'array', 'max:10'],
            'platforms.*' => ['string', 'max:40'],
        ];
    }
}
