<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use App\Modules\Pharmacy\Domain\Models\PharmacyStockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajustement d'inventaire d'un lot — PHARMA-003 (#7800). Delta signé non
 * nul, raison OBLIGATOIRE (journal immuable : la correction d'une erreur
 * passe par un ajustement inverse motivé, jamais par une réécriture).
 */
class StorePharmacyAdjustmentRequest extends FormRequest
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
            'batch_id' => ['required', 'integer', 'min:1'],
            'quantity_delta' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
            'reason' => ['required', 'string', 'max:500'],
            'type' => ['nullable', Rule::in([PharmacyStockMovement::TYPE_ADJUSTMENT, PharmacyStockMovement::TYPE_EXPIRY_WRITEOFF])],
        ];
    }
}
