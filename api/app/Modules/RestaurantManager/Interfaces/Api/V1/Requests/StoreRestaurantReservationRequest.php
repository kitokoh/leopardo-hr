<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use App\Modules\RestaurantManager\Domain\Models\RestaurantZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-601 (#6206) — Validation stricte de création d'une réservation.
 *
 * Branche, table et zone tenant-scopés ; `contact_phone` est une donnée
 * client (PII) — stockée en clair pour l'appel du jour, à chiffrer/redacter
 * au fil du chantier RGPD (spec §7). `reserved_at` doit être dans le futur.
 * Le conflit de créneau (±2h, même table) est tranché par le repository
 * (409) — critère d'acceptation.
 */
class StoreRestaurantReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReservationPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;
        $tenantExists = fn (Builder $query) => $query->where('company_id', $companyId);

        return [
            'branch_id' => ['required', 'integer', Rule::exists((new RestaurantBranch)->getTable(), 'id')->where($tenantExists)],
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'reserved_at' => ['required', 'date', 'after:now'],
            'covers' => ['nullable', 'integer', 'min:1', 'max:999'],
            'table_id' => ['nullable', 'integer', Rule::exists((new RestaurantTable)->getTable(), 'id')->where($tenantExists)],
            'zone_id' => ['nullable', 'integer', Rule::exists((new RestaurantZone)->getTable(), 'id')->where($tenantExists)],
            'customer_contact_id' => ['nullable', 'integer'],
            'deposit_minor' => ['nullable', 'integer', 'min:0'],
            'notes_redacted' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
