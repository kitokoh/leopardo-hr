<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Enums\RentalBookingStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelRentalBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Réservation de location (TRAVEL-213, issue #6026).
 *
 * La contrainte de non-chevauchement des dates pour un même véhicule n'est
 * **pas** enforced en base à ce stade (schéma seul) : le scope
 * `overlapping()` détecte les réservations concurrentes, mais c'est
 * l'Action du lot 3xx (TRAVEL-320) qui l'applique avant création.
 */
class TravelRentalBooking extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelRentalBookingFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'vehicle_id',
        'customer_contact_id',
        'start_date',
        'end_date',
        'total_amount_minor',
        'currency',
        'deposit_amount_minor',
        'payment_status',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount_minor' => 'integer',
        'deposit_amount_minor' => 'integer',
        'payment_status' => PaymentStatus::class,
        'status' => RentalBookingStatus::class,
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
        return 'RB-'.strtoupper(Str::random(10));
    }

    /**
     * Réservations du même véhicule dont l'intervalle [start_date, end_date]
     * chevauche celui fourni — hors statuts terminaux (annulée).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverlapping(
        Builder $query,
        int $vehicleId,
        string $startDate,
        string $endDate,
        ?int $excludingId = null
    ): Builder {
        $query->where('vehicle_id', $vehicleId)
            ->where('status', '!=', RentalBookingStatus::CANCELLED->value)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($excludingId !== null) {
            $query->where('id', '!=', $excludingId);
        }

        return $query;
    }

    /**
     * @return BelongsTo<TravelRentalVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TravelRentalVehicle::class);
    }
}
