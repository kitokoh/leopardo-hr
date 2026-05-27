<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\FormRequest;

class Enable2faPlatformAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
