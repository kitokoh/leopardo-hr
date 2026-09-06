<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Produit du catalogue B2B d'un tenant (BC-28 CATALOG, #6880).
 *
 * Prix indicatif stocké en **minor units** (entier) + devise ISO 4217 —
 * jamais de flottants (spec SOLUTION_CATALOGUE_B2B.md §4). Statut string
 * `draft|published` (enum PHP côté code). Meta libre (attributs, specs).
 * Tenant-scoped (`company_id`), slug unique par tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_minor
 * @property string $currency
 * @property string $unit
 * @property CatalogProductStatus $status
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class CatalogProduct extends Model
{
    use BelongsToCompany;

    protected $table = 'catalog_products';

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price_minor',
        'currency',
        'unit',
        'status',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogProductStatus::class,
            'meta' => 'array',
        ];
    }
}
