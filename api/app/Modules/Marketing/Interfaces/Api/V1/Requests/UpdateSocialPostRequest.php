<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'string', 'max:5000'],
            'target_platforms' => ['sometimes', 'array', 'min:1'],
            'target_platforms.*' => ['string', Rule::in(StoreSocialPostRequest::supportedPlatforms())],
            'media_paths' => ['nullable', 'array'],
            'media_paths.*' => ['string', 'max:2000'],
        ];
    }
}
