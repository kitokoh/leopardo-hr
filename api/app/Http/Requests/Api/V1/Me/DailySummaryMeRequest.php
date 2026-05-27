<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Me;

use Illuminate\Foundation\Http\FormRequest;

class DailySummaryMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
