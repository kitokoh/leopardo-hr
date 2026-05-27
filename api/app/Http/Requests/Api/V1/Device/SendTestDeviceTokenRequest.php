<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Device;

use Illuminate\Foundation\Http\FormRequest;

class SendTestDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:500'],
        ];
    }
}
