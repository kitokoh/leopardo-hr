<?php

namespace App\Http\Requests\Api\V1\Cameras;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class StoreCameraAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $durations = (array) Config::get('cameras.access_token_durations', []);

        return [
            'label' => ['nullable', 'string', 'max:150'],
            'granted_to_email' => ['nullable', 'email', 'max:150'],
            'granted_to_name' => ['nullable', 'string', 'max:100'],
            'expires_in_minutes' => array_filter([
                'required',
                'integer',
                empty($durations) ? 'min:1' : Rule::in($durations),
            ]),
            'permissions' => ['nullable', 'array'],
            'permissions.view' => ['sometimes', 'boolean'],
            'permissions.ptz' => ['sometimes', 'boolean'],
            'permissions.audio' => ['sometimes', 'boolean'],
            'ip_whitelist' => ['nullable', 'array'],
            'ip_whitelist.*' => ['ip'],
        ];
    }
}
