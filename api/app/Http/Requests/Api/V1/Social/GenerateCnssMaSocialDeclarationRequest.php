<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Social;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCnssMaSocialDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'year' => 'required|integer|min:2020|max:2099',
        ];
    }
}
