<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Annonce publicitaire (TRAVEL-907/908, issues #6110/#6111). Cycle submit → paid → validated → published → expired|archived ; visible seulement payée ET validée.
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
