<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Synchronisation de ventes (FUEL-014, #5808) — lot idempotent
 * (external_id par vente), borné à 500 entrées.
 */
class SyncFuelSalesRequest extends FormRequest
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
            'sales' => ['required', 'array', 'max:500'],
            'sales.*.station_id' => ['nullable', 'integer'],
            'sales.*.pump_id' => ['nullable', 'integer'],
            'sales.*.cash_session_id' => ['nullable', 'integer'],
            'sales.*.product' => ['required', 'string', 'max:80'],
            'sales.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'sales.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'sales.*.sale_time' => ['nullable', 'date'],
            'sales.*.source' => ['nullable', 'in:manual,kiosk,pos'],
            'sales.*.external_id' => ['required', 'string', 'max:120'],
        ];
    }
}
