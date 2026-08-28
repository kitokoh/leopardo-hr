<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envoi d'un email marketing CRM — Issue #5726.
 *
 * Le consentement marketing du contact est vérifié côté service
 * (fail-closed) avant tout envoi.
 */
class SendMarketingEmailRequest extends FormRequest
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
            'contact_id' => ['required', 'integer', 'min:1'],
            'to' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'campaign_send_id' => ['nullable', 'integer', 'min:1'],
            'company_id' => ['prohibited'],
        ];
    }
}
