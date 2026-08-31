<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\BookingSource;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Réservation multi-passagers (TRAVEL-209, issue #6022).
 *
 * `reference` est générée automatiquement (`GV-…`) si absente à la création.
 * `idempotency_key` garantit qu'une requête rejouée (retry réseau, double
 * clic guichet) ne crée jamais deux réservations pour le même tenant.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int $trip_id
 * @property BookingStatus $status
 * @property int $passenger_count
 * @property int $total_amount_minor
 * @property string $currency
 * @property BookingSource $booking_source
 * @property int|null $customer_contact_id
 * @property int|null $booked_by_user_id
 * @property PaymentStatus $payment_status
 * @property Carbon|null $cancelled_at
 * @property string $cancel_reason
 * @property Carbon|null $expires_at
 * @property string $idempotency_key
 * @property int $version
 * @property string $contact_email
 * @property string $contact_phone
 * @property bool $notify_consent
 * @property Carbon|null $consent_recorded_at
 * @property string $round_trip_group_id
 * @property int $return_booking_id
 * @property string $leg
 * @property int $corporate_account_id
 * @property int $quote_id
 * @property bool $billing_deferred
 * @property string $connection_group_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
        'cancelled_at',
        'cancel_reason',
        'expires_at',
        'idempotency_key',
        'version',
        'contact_email',
        'contact_phone',
        'notify_consent',
        'consent_recorded_at',
        'round_trip_group_id',
        'return_booking_id',
        'leg',
        'corporate_account_id',
        'quote_id',
        'billing_deferred',
        'connection_group_id',
    ];

    protected $casts = [
        'passenger_count' => 'integer',
        'total_amount_minor' => 'integer',
        'status' => BookingStatus::class,
        'booking_source' => BookingSource::class,
        'payment_status' => PaymentStatus::class,
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'version' => 'integer',
        'notify_consent' => 'boolean',
        'consent_recorded_at' => 'datetime',
        'return_booking_id' => 'integer',
        'corporate_account_id' => 'integer',
        'quote_id' => 'integer',
        'billing_deferred' => 'boolean',
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

    /**
     * @return HasMany<TravelTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(TravelTicket::class, 'booking_id');
    }

    /**
     * @return HasMany<TravelPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TravelPayment::class, 'booking_id');
    }
}
