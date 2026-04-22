<?php

namespace App\Http\Requests\Api\V1\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
