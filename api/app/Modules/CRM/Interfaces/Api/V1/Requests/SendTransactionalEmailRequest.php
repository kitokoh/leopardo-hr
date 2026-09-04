<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Envoi d'un email transactionnel CRM — Issue #5726.
 */
class SendTransactionalEmailRequest extends FormRequest
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
            'to' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'contact_id' => ['nullable', 'integer', 'min:1'],
            'campaign_send_id' => ['nullable', 'integer', 'min:1'],
            'company_id' => ['prohibited'],
        ];
    }
}
