<?php

declare(strict_types=1);

namespace App\Modules\Growth\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Candidature partenaire (issue #4383 : coordonnées jamais écrasées).
 */
class ApplyPartnerRequest extends FormRequest
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
        return ['type' => ['required', 'in:individual,agency,accountant'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'payment_details' => ['nullable', 'string']];
    }
}
