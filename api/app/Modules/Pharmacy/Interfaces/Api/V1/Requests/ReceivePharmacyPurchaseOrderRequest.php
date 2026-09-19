<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réception d'une commande d'achat — PHARMA-004 (#7801). Chaque ligne
 * reçue porte sa quantité, son n° de lot et sa péremption (le lot est créé
 * via PharmacyStockService::receive(), #7800). Réception partielle
 * supportée ; la sur-réception est refusée par le service.
 */
class ReceivePharmacyPurchaseOrderRequest extends FormRequest
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
            'receipts' => ['required', 'array', 'min:1'],
            'receipts.*.line_id' => ['required', 'integer', 'min:1'],
            'receipts.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'receipts.*.batch_number' => ['required', 'string', 'max:64'],
            'receipts.*.expiry_date' => ['required', 'date', 'after:today'],
            'receipts.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
