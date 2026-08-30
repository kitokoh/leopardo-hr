<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Réservation client (RESTO-210, issue #6175).
 *
 * `reference` est générée automatiquement (`RSV-…`) et `idempotency_key`
 * (uuid) si absentes à la création — la paire (tenant, référence) et
 * (tenant, idempotency_key) sont uniques. `reserved_at` est l'heure d'arrivée
 * prévue ; `deposit_minor` est l'acompte éventuel (minor units).
 */
class RestaurantReservation extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'reference',
        'customer_contact_id',
        'contact_name',
        'contact_phone',
        'reserved_at',
        'covers',
        'table_id',
        'zone_id',
        'status',
        'deposit_minor',
        'notes_redacted',
        'idempotency_key',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'covers' => 'integer',
        'deposit_minor' => 'integer',
        'status' => ReservationStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reservation): void {
            if (empty($reservation->reference)) {
                $reservation->reference = self::generateReference();
            }

            if (empty($reservation->idempotency_key)) {
                $reservation->idempotency_key = (string) Str::uuid();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'RSV-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<RestaurantZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(RestaurantZone::class, 'zone_id');
    }
}
