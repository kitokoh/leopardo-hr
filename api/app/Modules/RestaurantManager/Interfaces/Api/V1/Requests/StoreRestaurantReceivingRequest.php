<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantIngredient;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-503 (#6202) — Validation stricte d'une réception de marchandises.
 *
 * Réception directe (fournisseur + lignes) ou rattachée à un bon de commande
 * (réception partielle possible via les quantités). `reference` (unique par
 * tenant) rend la réception idempotente : rejouer la même référence → 409
 * (déjà réceptionnée) ; sans référence → générée (RCV-…). Les entrées de
 * stock et le coût moyen pondéré sont calculés serveur (ReceivingService).
 */
class StoreRestaurantReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReceivingPolicy::create() tranche l'autorisation
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
            'purchase_order_id' => ['nullable', 'integer', Rule::exists((new RestaurantPurchaseOrder)->getTable(), 'id')->where($tenantExists)],
            'supplier_id' => ['nullable', 'integer', Rule::exists((new RestaurantSupplier)->getTable(), 'id')->where($tenantExists)],
            'reference' => ['nullable', 'string', 'max:40'],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.ingredient_id' => ['required', 'integer', Rule::exists((new RestaurantIngredient)->getTable(), 'id')->where($tenantExists)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price_minor' => ['required', 'integer', 'min:0', 'max:999999999'],
        ];
    }
}
