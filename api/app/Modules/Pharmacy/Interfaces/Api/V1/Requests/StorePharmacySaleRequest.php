<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use App\Modules\Pharmacy\Domain\Models\PharmacySale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Création d'une vente comptoir — PHARMA-005 (#7802). Le client n'envoie
 * QUE des identifiants et quantités : prix, taxes et totaux sont calculés
 * serveur (PharmacySaleService).
 */
class StorePharmacySaleRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'payment_method' => ['required', Rule::in(PharmacySale::PAYMENT_METHODS)],
            'customer_name' => ['nullable', 'string', 'max:191'],
            'prescription_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
