<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Réception (partielle ou totale) d'une commande d'achat — PHARMA-004
 * (#7801). Chaque ligne reçue porte quantité, n° de lot, péremption et coût.
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'min:1'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.batch_number' => ['required', 'string', 'max:64'],
            'lines.*.expiry_date' => ['required', 'date_format:Y-m-d'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
