<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enregistrement d'un paiement sur un document (issue #5229).
 */
class PaymentRegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,bank_transfer,check,card,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => __('accounting.validation.amount_required'),
            'amount.min' => __('accounting.validation.amount_min'),
            'method.in' => __('accounting.validation.method_invalid'),
        ];
    }
}
