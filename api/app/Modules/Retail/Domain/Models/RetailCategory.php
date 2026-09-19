<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Catégorie de produits du module Retail d'un tenant (BC-17 RETAIL, #7672).
 *
 * Tenant-scoped (`company_id`), slug unique par tenant, hiérarchie plate
 * v1 (`parent_id` nullable, sans FK — conventions migrations tenant §2.6,
 * pattern Catalog #6880).
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
class RetailCategory extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_categories';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'parent_id',
        'position',
    ];

    /**
     * @return HasMany<RetailProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(RetailProduct::class, 'category_id');
    }
}
