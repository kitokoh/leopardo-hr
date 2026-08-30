<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'une vente FuelStation (FUEL-008, #5802).
 *
 * Le montant n'est JAMAIS accepté (calculé serveur). `external_id` rend le
 * rejeu idempotent. La session de caisse doit appartenir au tenant ;
 * station/pompe sont BIGINTs et validées tenant-scopées (FKs composites
 * (x, company_id) → fuel_stations/fuel_pumps).
 */
class StoreFuelSaleRequest extends FormRequest
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
                'nullable',
                'integer',
                Rule::exists('fuel_stations', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'pump_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_pumps', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'cash_session_id' => [
                'nullable',
                'integer',
                Rule::exists('fuel_cash_sessions', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'product' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'sale_time' => ['nullable', 'date'],
            'source' => ['nullable', Rule::in(FuelSale::SOURCES)],
            'external_id' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'customer_contact_id' => [
                'nullable',
                'integer',
                // FUEL-016 (#5810) : référence CRM client par valeur, validée
                // dans le tenant courant — JAMAIS les leads commerciaux
                // plateforme (crm_leads) ni un contact d'un autre tenant.
                Rule::exists('crm_contacts', 'id')->where(
                    fn (Builder $query): Builder => $query->where('company_id', $actor?->company_id)
                ),
            ],
            'marketing_consent' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
