<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\InventoryCountStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantInventoryCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Session de comptage d'inventaire (RESTO-207, issue #6172).
 *
 * Cycle draft → submitted → approved ; `counted_by_user_id` est le compteur,
 * `approved_by`/`approved_at` documentent la validation.
 */
class RestaurantInventoryCount extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantInventoryCountFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'counted_at',
        'status',
        'counted_by_user_id',
        'approved_by',
        'approved_at',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'status' => InventoryCountStatus::class,
        'approved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantInventoryCountItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantInventoryCountItem::class, 'count_id');
    }
}
