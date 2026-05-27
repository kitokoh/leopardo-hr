<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Privacy;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeletionRequestPrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
