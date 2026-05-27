<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SSO;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureSSORequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:saml,oidc',
            'entity_id' => 'required|string|url',
            'sso_url' => 'required|string|url',
            'slo_url' => 'nullable|string|url',
            'certificate' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_secret' => 'nullable|string',
        ];
    }
}
