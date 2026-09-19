<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Emplacement de stock du module Retail d'un tenant (BC-17 RETAIL, #7673).
 *
 * Boutique (`store`) ou entrepot (`warehouse`), code unique par tenant.
 * Tenant-scoped (`company_id`), sans FK (conventions migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailLocation extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_locations';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RetailStockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(RetailStockLevel::class, 'location_id');
    }
}
