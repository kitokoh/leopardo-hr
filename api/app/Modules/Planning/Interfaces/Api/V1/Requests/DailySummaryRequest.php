<?php

declare(strict_types=1);

namespace App\Modules\Planning\Interfaces\Api\V1\Requests;

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
