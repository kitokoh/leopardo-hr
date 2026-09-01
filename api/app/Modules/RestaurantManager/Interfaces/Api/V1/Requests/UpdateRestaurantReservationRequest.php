<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-601 (#6206) — Mise à jour d'une réservation (en attente uniquement).
 *
 * Le statut et la référence sont immutables : les transitions passent par
 * les actions dédiées (confirm/check-in/no-show/cancel).
 */
class UpdateRestaurantReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReservationPolicy::update() tranche
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'branch_id' => ['sometimes', 'integer', Rule::exists('restaurant_branches', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'contact_name' => ['sometimes', 'string', 'max:150'],
            'contact_phone' => ['sometimes', 'string', 'max:40'],
            'reserved_at' => ['sometimes', 'date'],
            'covers' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'table_id' => ['nullable', 'integer', Rule::exists('restaurant_tables', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'zone_id' => ['nullable', 'integer', Rule::exists('restaurant_zones', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'notes_redacted' => ['nullable', 'string', 'max:1000'],
            'status' => ['prohibited'],
            'reference' => ['prohibited'],
            'deposit_minor' => ['prohibited'],
            'idempotency_key' => ['prohibited'],
        ];
    }
}
