<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-606 (#6211) — Programme de fidélité (création).
 * RESTO-606 (#6211) — Validation stricte de création d'un programme fidélité.
 *
 * Bornes métier : `points_per_amount_minor` et `redeem_rate_minor` strictement
 * positifs (un taux nul rendrait le crédit incohérent), `is_active` booléen.
 * Un seul programme actif est garanti par l'Action/contrôleur.
 */
class StoreRestaurantLoyaltyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantLoyaltyProgramPolicy::create() tranche
        return true; // RestaurantLoyaltyProgramPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'points_per_amount_minor' => ['required', 'integer', 'min:1'],
            'redeem_rate_minor' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        return [
            'points_per_amount_minor' => ['sometimes', 'integer', 'min:1'],
            'redeem_rate_minor' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean', Rule::in([true, false])],
        ];
    }
}
