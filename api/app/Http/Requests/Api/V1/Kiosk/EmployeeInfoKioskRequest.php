<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeInfoKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:150'],
        ];
    }
}
