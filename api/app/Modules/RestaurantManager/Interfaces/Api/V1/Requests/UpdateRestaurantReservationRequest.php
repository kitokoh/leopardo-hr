<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantTable;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-601 (#6206) — Validation stricte de mise à jour d'une réservation.
 */
class UpdateRestaurantReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReservationPolicy::update() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        return [
            'contact_name' => ['sometimes', 'string', 'max:150'],
            'contact_phone' => ['sometimes', 'string', 'max:40'],
            'reserved_at' => ['sometimes', 'date', 'after:now'],
            'covers' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'table_id' => ['sometimes', 'nullable', 'integer', Rule::exists((new RestaurantTable)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
            'notes_redacted' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
