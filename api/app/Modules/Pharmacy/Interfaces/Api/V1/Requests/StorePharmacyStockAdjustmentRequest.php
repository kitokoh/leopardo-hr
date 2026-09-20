<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajustement d'inventaire — PHARMA-003 (#7800). Delta signé non nul, raison
 * obligatoire (journal immuable : chaque ajustement est motivé et tracé).
 */
class StorePharmacyStockAdjustmentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'type' => ['nullable', Rule::in(['adjustment', 'expiry_writeoff'])],
        ];
    }
}
