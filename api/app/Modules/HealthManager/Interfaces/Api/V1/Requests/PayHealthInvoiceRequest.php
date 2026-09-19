<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Interfaces\Api\V1\Requests;

use App\Modules\HealthManager\Domain\Models\HealthInvoicePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Encaissement d'une facture de soins — HC-007 (#7791).
 *
 * Montant strictement positif ; le contrôle de sur-paiement (cumul ≤ total)
 * est porté par le service SOUS VERROU (HealthInvoiceService@pay).
 */
class PayHealthInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC porté par la policy (HealthInvoicePolicy@update).
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'method' => ['required', Rule::in(HealthInvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:191'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
