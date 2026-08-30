<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remboursement (TRAVEL-808, issue #6098).
 *
 * Partiel par passager ou complet ; idempotent par refund_key (rejeu sans
 * double remboursement). Pénalité calculée serveur (règles d'élasticité,
 * surclassées par les politiques d'annulation TRAVEL-813/#6103).
 */
class TravelRefund extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRefundFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'passenger_id',
        'amount_minor',
        'penalty_minor',
        'currency',
        'reason',
        'refund_key',
        'refunded_by_user_id',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'penalty_minor' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'booking_id');
    }
}
