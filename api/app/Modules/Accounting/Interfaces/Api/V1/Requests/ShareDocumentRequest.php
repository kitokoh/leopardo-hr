<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue #9765 — création d'un partage tokenisé de document comptable.
 *
 * Le modèle `AccountingDocumentShare`, le service `DocumentShareService` et le
 * portail public existaient déjà ; seule la route de CRÉATION manquait, donc
 * aucun jeton ne pouvait être généré depuis la plateforme.
 *
 * `expires_in_days` est borné : un partage ne peut être ni immortel (365 j
 * maximum) ni instantané (1 j minimum). Le défaut (14 j) est appliqué par
 * `DocumentShareService::DEFAULT_TTL_DAYS`.
 */
class ShareDocumentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => __('accounting.validation.share_email_invalid'),
            'expires_in_days.min' => __('accounting.validation.share_expiry_min'),
            'expires_in_days.max' => __('accounting.validation.share_expiry_max'),
        ];
    }
}
