<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-805 (#6226) — Validation de commande en ligne publique.
 *
 * Aucun montant n'est accepté (totaux recalculés serveur) ; les produits
 * doivent appartenir au tenant (exists tenant-scopé via le scope
 * BelongsToCompany posé par le middleware `restaurant.public.shop`).
 */
class StoreOnlineOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'accès est déjà borné par le jeton de boutique publique
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantBranch)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $this->companyId())
                ),
            ],
            'order_type' => ['sometimes', 'string', Rule::in(['takeaway', 'delivery'])],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists((new RestaurantProduct)->getTable(), 'id')->where(
                    fn (Builder $query) => $query->where('company_id', $this->companyId())
                ),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    private function companyId(): ?string
    {
        return app()->bound('current_company') ? currentCompany()->id : null;
    }
}
