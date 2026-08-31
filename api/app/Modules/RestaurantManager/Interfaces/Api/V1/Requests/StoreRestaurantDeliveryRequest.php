<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-605 (#6210) — Création d'une livraison rattachée à une commande
 * `delivery` du tenant courant. Le frais est recalculé/validé serveur.
 * RESTO-605 (#6210) — Validation de création d'une livraison.
 *
 * `zone_id` optionnelle : le frais est toujours recalculé serveur depuis la
 * zone (CreateDeliveryAction). La zone doit appartenir au tenant courant.
 */
class StoreRestaurantDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantDeliveryPolicy::create() tranche
        return true; // RestaurantDeliveryPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        return [
            'order_id' => ['required', 'integer', Rule::exists('restaurant_orders', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'zone_id' => ['nullable', 'integer', Rule::exists('restaurant_delivery_zones', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'fee_minor' => ['required', 'integer', 'min:0'],
        ];
    }
        $companyId = $this->companyId();

        return [
            'zone_id' => ['nullable', 'integer', Rule::exists((new RestaurantDeliveryZone)->getTable(), 'id')->where(fn (Builder $query) => $query->where('company_id', $companyId))],
        ];
    }

    private function companyId(): ?string
    {
        $user = $this->user();
        if ($user instanceof Employee && $user->company_id !== null) {
            return $user->company_id;
        }

        return app()->bound('current_company') ? currentCompany()->id : null;
    }
}
