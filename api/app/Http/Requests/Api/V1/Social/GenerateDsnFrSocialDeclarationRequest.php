<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Social;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDsnFrSocialDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
        ];
    }
}
