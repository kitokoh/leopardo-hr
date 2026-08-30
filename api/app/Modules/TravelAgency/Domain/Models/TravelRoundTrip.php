<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\RoundTripStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRoundTripFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Aller-retour combiné (TRAVEL-802, issue #6093).
 *
 * Lie deux réservations (aller + retour) d'un même tenant ; le statut est
 * dérivé du statut des deux réservations (jamais persisté — chaque sens reste
 * une réservation standard, annulable par sens).
 */
class TravelRoundTrip extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRoundTripFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'booking_outbound_id',
        'booking_return_id',
        'idempotency_key',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $roundTrip): void {
            if (empty($roundTrip->reference)) {
                $roundTrip->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'RT-'.strtoupper(Str::random(10));
    }

    /**
     * Statut dérivé des deux réservations liées.
     */
    public function status(): RoundTripStatus
    {
        $outbound = $this->bookingOutbound;
        $return = $this->bookingReturn;

        $outboundCancelled = $outbound !== null && in_array($outbound->status->value, ['cancelled', 'refunded'], true);
        $returnCancelled = $return !== null && in_array($return->status->value, ['cancelled', 'refunded'], true);

        if ($outboundCancelled && $returnCancelled) {
            return RoundTripStatus::CANCELLED;
        }

        if ($outboundCancelled || $returnCancelled) {
            return RoundTripStatus::PARTIALLY_CANCELLED;
        }

        return RoundTripStatus::ACTIVE;
    }

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function bookingOutbound(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'booking_outbound_id');
    }

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function bookingReturn(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'booking_return_id');
    }
}
