<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-504 (#6203) — Validation de création d'un inventaire physique.
 *
 * Les lignes de comptage sont pré-remplies serveur depuis les niveaux de
 * stock courants de la branche (quantités attendues) — le client ne transmet
 * que la branche.
 */
class StoreRestaurantInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantInventoryCountPolicy::create() tranche
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
        ];
    }
}
