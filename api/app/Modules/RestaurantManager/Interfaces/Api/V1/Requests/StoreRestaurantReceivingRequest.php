<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RESTO-503 (#6202) — Validation stricte d'une réception de marchandises.
 *
 * La référence est optionnelle (auto-générée `RCV-*`) ; si fournie, elle
 * doit être unique par tenant (idempotence du rejeu client). Les lignes
 * (ingrédients, quantités, prix) sont validées dans le tenant courant.
 */
class StoreRestaurantReceivingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RestaurantReceivingPolicy::create() tranche
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
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('restaurant_purchase_orders', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'supplier_id' => ['nullable', 'integer', Rule::exists('restaurant_suppliers', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'reference' => ['nullable', 'string', 'max:40', Rule::unique('restaurant_receivings', 'reference')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'note_redacted' => ['nullable', 'string', 'max:1000'],
            // `lines` est le nom historique du contrat (tests, clients deja
            // integres) ; `items` celui valide a l'origine. Les deux sont
            // acceptes, mais un seul doit etre fourni : le controleur lit
            // `lines` en priorite puis retombe sur `items`. Sans cette
            // tolerance, `POST /restaurant/receivings` repondait 500
            // (`Undefined array key "lines"`) sur tout appel conforme au
            // schema valide — cf. #8396.
            'lines' => ['required_without:items', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required_with:lines', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit_price_minor' => ['required_with:lines', 'integer', 'min:0'],
            'items' => ['required_without:lines', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required_with:items', 'integer', Rule::exists('restaurant_ingredients', 'id')->where(fn (Builder $q) => $q->where('company_id', $actor->company_id))],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price_minor' => ['required_with:items', 'integer', 'min:0'],
        ];
    }
}
