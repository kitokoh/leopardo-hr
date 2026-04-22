<?php

namespace App\Http\Requests\Api\V1\Estimation;

use Illuminate\Foundation\Http\FormRequest;

class DailySummaryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
