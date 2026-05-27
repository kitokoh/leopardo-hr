<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformCompanyFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ];
    }
}
