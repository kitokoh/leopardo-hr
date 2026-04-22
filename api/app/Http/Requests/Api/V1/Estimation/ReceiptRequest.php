<?php

namespace App\Http\Requests\Api\V1\Estimation;

use Illuminate\Foundation\Http\FormRequest;

class ReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
