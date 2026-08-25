<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rapprochement manuel d'une ligne de relevé avec un paiement (issue #5435).
 */
class MatchBankStatementLineRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'payment_id' => ['required', 'integer', 'exists:accounting_payments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_id.required' => __('accounting.validation.bank_payment_required'),
            'payment_id.exists' => __('accounting.validation.bank_payment_exists'),
        ];
    }
}
