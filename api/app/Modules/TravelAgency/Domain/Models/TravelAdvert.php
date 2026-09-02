<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelAdvertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Annonce payante (TRAVEL-907/908, issues #6110/#6111).
 *
 * Prix calculé serveur depuis la grille ; visible uniquement si payée ET
 * validée ET non expirée.
 *
 * @property int $id
 * @property string $company_id
 * @property int $advert_type_id
 * @property int $advert_position_id
 * @property string $title
 * @property string $content_redacted
 * @property int|null $image_asset_id
 * @property int $price_minor
 * @property string $currency
 * @property AdvertStatus $status
 * @property string|null $payment_reference
 * @property Carbon|null $paid_at
 * @property int|null $validated_by_user_id
 * @property Carbon|null $validated_at
 * @property string|null $rejected_reason
 * @property int $validity_days
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property int|null $created_by_user_id
 *
 * @mixin Builder<static>
 */
class TravelAdvert extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelAdvertFactory> */
    use HasFactory;

    protected $table = 'travel_adverts';

    protected $fillable = [
        'company_id',
        'advert_type_id',
        'advert_position_id',
        'title',
        'content_redacted',
        'image_asset_id',
        'price_minor',
        'currency',
        'status',
        'payment_reference',
        'paid_at',
        'validated_by_user_id',
        'validated_at',
        'rejected_reason',
        'validity_days',
        'starts_at',
        'expires_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'status' => AdvertStatus::class,
        'paid_at' => 'datetime',
        'validated_at' => 'datetime',
        'validity_days' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isVisible(): bool
    {
        return $this->status === AdvertStatus::VALIDATED
            && $this->paid_at !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @return BelongsTo<TravelAdvertType, $this>
     */
    public function advertType(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertType::class, 'advert_type_id');
    }

    /**
     * @return BelongsTo<TravelAdvertPosition, $this>
     */
    public function advertPosition(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertPosition::class, 'advert_position_id');
    }
}
