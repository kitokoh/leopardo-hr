<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Me;

use Illuminate\Foundation\Http\FormRequest;

class QuickEstimateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
