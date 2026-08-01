<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shareable_type' => ['required', 'string', Rule::in(['folder', 'document'])],
            'shareable_id' => ['required', 'integer'],
            'shared_via' => ['required', 'string', Rule::in(['email', 'link'])],
            'shared_with_email' => ['required_if:shared_via,email', 'nullable', 'email', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
