<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'un paiement sur une facture de soins (HC-007, #7791).
 * Montant strictement positif, mode borné. Le contrôle du SOLDE (paiement
 * partiel → partially_paid, solde exact → paid, sur-paiement → 422) est
 * fait SOUS TRANSACTION côté contrôleur, facture verrouillée.
 */
class StoreHealthInvoicePaymentRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'method' => ['required', 'string', Rule::in(HealthInvoicePayment::METHODS)],
            'paid_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
