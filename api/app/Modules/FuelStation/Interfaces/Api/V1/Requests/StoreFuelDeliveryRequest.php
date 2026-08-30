<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une livraison FuelStation (FUEL-009, #5803).
 *
 * `idempotency_key` obligatoire : un rejeu retourne la livraison existante
 * (zéro doublon). Station/tank validées tenant-scopées (FKs composites
 * (x, company_id) → fuel_stations/fuel_tanks).
 */
class StoreFuelDeliveryRequest extends FormRequest
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
        /** @var Employee|null $actor */
        $actor = $this->user();

        return [
            'station_id' => [
                'required',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'tank_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_tanks', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'product_type' => ['required', 'string', 'max:40'],
            'quantity_minor' => ['required', 'integer', 'gt:0', 'max:999999999999'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'delivered_at' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
