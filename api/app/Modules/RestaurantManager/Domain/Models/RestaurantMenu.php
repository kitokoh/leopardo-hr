<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantMenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Menu (carte ou formule) proposé par une branche — RESTO-204, issue #6169.
 *
 * `code` est unique par tenant ; `price_minor` est un prix indicatif (le prix
 * réel est la somme des items). `starts_at`/`ends_at` bornent la période de validité.
 */
class RestaurantMenu extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantMenuFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'price_minor',
        'currency',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
        'currency' => 'DZD',
        'price_minor' => 0,
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => RestaurantRecordStatus::class,
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return HasMany<RestaurantMenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantMenuItem::class, 'menu_id');
    }
}
