<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\PromotionDiscountType;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantPromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Promotion (remise, offre) applicable aux commandes — RESTO-212, issue #6177.
 *
 * `code` est unique par tenant ; `discount_type` distingue le pourcentage de
 * la remise en montant fixe (`value_minor`, minor units). `max_uses` borne le
 * nombre total d'utilisations.
 */
class RestaurantPromotion extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantPromotionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'title',
        'discount_type',
        'value_minor',
        'min_order_minor',
        'starts_at',
        'ends_at',
        'max_uses',
        'used_count',
        'is_active',
    ];

    protected $attributes = [
        'discount_type' => 'percent',
        'value_minor' => 0,
        'used_count' => 0,
        'is_active' => true,
    ];

    protected $casts = [
        'discount_type' => PromotionDiscountType::class,
        'value_minor' => 'integer',
        'min_order_minor' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }
}
