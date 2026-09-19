<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Produit du module Retail d'un tenant (BC-17 RETAIL, #7672).
 *
 * Prix de vente et coût d'achat stockés en **minor units** (entier) +
 * devise ISO 4217 — jamais de flottants (pattern Catalog #6880). SKU
 * unique par tenant (référence interne), code-barres optionnel. Statut
 * string `draft|published|archived` (enum PHP côté code). Meta libre
 * (attributs, specs). Tenant-scoped (`company_id`), slug unique par tenant.
 *
 * Marketplace Leopardo Marché (#7807) : `online_visible` = opt-in de
 * publication publique (indépendant de `status` — un produit n'est visible
 * sur la vitrine que si published ET online_visible ET boutique enabled),
 * `image_url` = visuel public (URL absolue).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property string|null $barcode
 * @property string|null $description
 * @property int $price_minor
 * @property int|null $cost_minor
 * @property string $currency
 * @property string|null $unit
 * @property RetailProductStatus $status
 * @property bool $online_visible
 * @property string|null $image_url
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailProduct extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_products';

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'price_minor',
        'cost_minor',
        'currency',
        'unit',
        'status',
        'online_visible',
        'image_url',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RetailProductStatus::class,
            'online_visible' => 'boolean',
            'meta' => 'array',
        ];
    }
}
