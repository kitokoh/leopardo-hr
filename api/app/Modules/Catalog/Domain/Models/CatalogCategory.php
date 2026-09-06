<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Catégorie de produits du catalogue B2B d'un tenant (BC-28 CATALOG, #6880).
 *
 * Tenant-scoped (`company_id`), slug unique par tenant, hiérarchie plate
 * v1 (`parent_id` nullable, sans FK — conventions migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class CatalogCategory extends Model
{
    use BelongsToCompany;

    protected $table = 'catalog_categories';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'parent_id',
        'position',
    ];

    /**
     * @return HasMany<CatalogProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(CatalogProduct::class, 'category_id');
    }
}
