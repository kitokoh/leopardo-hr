<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class PunchKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
        ];
    }
}
