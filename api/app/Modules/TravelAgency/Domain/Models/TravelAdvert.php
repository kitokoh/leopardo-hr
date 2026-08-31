<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Annonce publicitaire (TRAVEL-907/908, issues #6110/#6111). Cycle submit → paid → validated → published → expired|archived ; visible seulement payée ET validée.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $type_id
 * @property string $position_id
 * @property string $title
 * @property string $body_redacted
 * @property string $image_path
 * @property int $character_count
 * @property int $price_minor
 * @property string $currency
 * @property string $status
 * @property Carbon|null $paid_at
 * @property string $payment_id
 * @property Carbon|null $validated_at
 * @property string $validated_by_user_id
 * @property Carbon|null $published_at
 * @property Carbon|null $valid_until
 * @property string $moderation_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelAdvert extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'type_id', 'position_id', 'title', 'body_redacted', 'image_path', 'character_count', 'price_minor', 'currency', 'status', 'paid_at', 'payment_id', 'validated_at', 'validated_by_user_id', 'published_at', 'valid_until', 'moderation_note',
    ];

    protected $casts = [
        'character_count' => 'integer',
        'price_minor' => 'integer',
        'paid_at' => 'datetime',
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
        'valid_until' => 'datetime',
    ];
}
