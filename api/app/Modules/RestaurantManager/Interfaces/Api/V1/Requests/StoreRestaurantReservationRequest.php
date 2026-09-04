<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-601 (#6206) — Validation stricte de création d'une réservation.
 *
 * `idempotency_key` unique par tenant (rejeu client sans doublon) ; la table
 * et la zone sont validées dans le tenant courant ; la capacité de la table
 * est contrôlée (422 si couverts > capacité). Le conflit de créneau est
 * tranché par le service (409).
 */
class StoreRestaurantReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReservationPolicy::create() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'branch_id' => ['required', 'integer', Rule::exists('restaurant_branches', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'reserved_at' => ['required', 'date'],
            'covers' => ['required', 'integer', 'min:1', 'max:50'],
            'table_id' => ['nullable', 'integer', Rule::exists('restaurant_tables', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'zone_id' => ['nullable', 'integer', Rule::exists('restaurant_zones', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'deposit_minor' => ['nullable', 'integer', 'min:0'],
            'notes_redacted' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64', Rule::unique('restaurant_reservations', 'idempotency_key')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('table_id')) {
                return;
            }

            $table = \App\Modules\RestaurantManager\Domain\Models\RestaurantTable::query()
                ->where('company_id', $this->user()->company_id)
                ->find($this->input('table_id'));

            if ($table !== null && (int) $table->capacity < (int) $this->input('covers')) {
                $validator->errors()->add('covers', 'La table sélectionnée ne peut accueillir que '.$table->capacity.' couverts.');
            }
        });
    }
}
