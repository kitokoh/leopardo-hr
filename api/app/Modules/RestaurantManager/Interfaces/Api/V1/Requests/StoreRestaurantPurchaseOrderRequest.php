<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-502 (#6201) — Validation stricte de création d'un bon de commande.
 *
 * Branche et fournisseur tenant-scopés ; `reference` optionnelle (générée
 * PO-… si absente, unique par tenant) ; le total n'est jamais accepté du
 * client (recalcul serveur à l'ajout des lignes).
 */
class StoreRestaurantPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantPurchaseOrderPolicy::create() tranche l'autorisation
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $companyId = $user instanceof Employee ? $user->company_id : null;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantSupplier)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $companyId)
                ),
            ],
            'reference' => ['nullable', 'string', 'max:40'],
            'expected_at' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
