<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TRAVEL-907/908 (#6110/#6111) — Annonce payante (tenant-scoped).
 *
 * Prix calculé serveur au moment de la soumission (snapshot du tarif
 * type × position dans la devise du tenant) ; cycle
 * draft → paid → published → expired (+ rejected, archived) ; une annonce
 * n'est visible qu'une fois payée ET validée par `travel.manage`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $advert_type_id
 * @property int $advert_position_id
 * @property string $title
 * @property string $body_redacted
 * @property string|null $image_path
 * @property int $character_count
 * @property int $price_image_minor
 * @property int $price_character_minor
 * @property int $total_minor
 * @property string $currency
 * @property string $status
 * @property int|null $payment_id
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property int|null $validated_by_user_id
 * @property \Illuminate\Support\Carbon|null $validated_at
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $moderation_note
 *
 * @mixin Builder<static>
 */
class TravelAdvert extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<\Database\Factories\TravelAdvertFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ARCHIVED = 'archived';

    /** Durée de validité d'une annonce publiée (jours). */
    public const VALIDITY_DAYS = 30;

    protected $table = 'travel_adverts';

    protected $fillable = [
        'company_id',
        'advert_type_id',
        'advert_position_id',
        'title',
        'body_redacted',
        'image_path',
        'character_count',
        'price_image_minor',
        'price_character_minor',
        'total_minor',
        'currency',
        'status',
        'payment_id',
        'paid_at',
        'validated_by_user_id',
        'validated_at',
        'published_at',
        'expires_at',
        'moderation_note',
    ];

    protected $casts = [
        'character_count' => 'integer',
        'price_image_minor' => 'integer',
        'price_character_minor' => 'integer',
        'total_minor' => 'integer',
        'paid_at' => 'datetime',
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelAdvertType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertType::class, 'advert_type_id');
    }

    /**
     * @return BelongsTo<TravelAdvertPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(TravelAdvertPosition::class, 'advert_position_id');
    }

    /**
     * @return BelongsTo<TravelPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(TravelPayment::class, 'payment_id');
    }
}
