<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Me;

use Illuminate\Foundation\Http\FormRequest;

class MonthlySummaryMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
