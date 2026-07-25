<?php

declare(strict_types=1);

namespace App\Modules\Marketing\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketingLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:80'],
            'type' => ['required', 'string', 'in:signup,demo_request,newsletter,contact'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'locale' => ['sometimes', 'string', 'max:5'],
            'country' => ['sometimes', 'nullable', 'string', 'max:2'],
            'page' => ['sometimes', 'nullable', 'string', 'max:300'],
            'source' => ['sometimes', 'nullable', 'string', 'max:120'],
            'campaign' => ['sometimes', 'nullable', 'string', 'max:120'],
            'ip' => ['sometimes', 'nullable', 'string', 'max:64'],
            'referrer' => ['sometimes', 'nullable', 'string', 'max:500'],
            'payload' => ['sometimes', 'nullable', 'array'],
            'crm_forwarded' => ['sometimes', 'boolean'],
            'email_forwarded' => ['sometimes', 'boolean'],
            'captured_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
