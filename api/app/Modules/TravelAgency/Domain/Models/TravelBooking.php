<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelBookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Réservation multi-passagers (TRAVEL-209, issue #6022).
 *
 * `reference` est générée automatiquement (`GV-…`) si absente à la création.
 * `idempotency_key` garantit qu'une requête rejouée (retry réseau, double
 * clic guichet) ne crée jamais deux réservations pour le même tenant.
 */
class TravelBooking extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'trip_id',
        'status',
        'passenger_count',
        'total_amount_minor',
        'currency',
        'booking_source',
        'customer_contact_id',
        'booked_by_user_id',
        'payment_status',
        'expires_at',
        'idempotency_key',
        'version',
    ];

    protected $casts = [
        'passenger_count' => 'integer',
        'total_amount_minor' => 'integer',
        'status' => BookingStatus::class,
        'booking_source' => BookingSource::class,
        'payment_status' => PaymentStatus::class,
        'expires_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            if (empty($booking->reference)) {
                $booking->reference = self::generateReference();
            }

            if (empty($booking->idempotency_key)) {
                $booking->idempotency_key = (string) Str::uuid();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'GV-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<TravelTrip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TravelTrip::class, 'trip_id');
    }

    /**
     * @return HasMany<TravelPassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(TravelPassenger::class, 'booking_id');
    }
}
