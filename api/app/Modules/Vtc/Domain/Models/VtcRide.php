<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\VtcRideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Course VTC/taxi — agrégat racine (BC-34 VTC, VTC-02/#8358).
 *
 * Référence `VTC-YYYY-NNNNNN` unique par tenant, `idempotency_key` unique
 * par tenant (zéro doublon de création, VTC-03), estimation persistée en
 * minor units, cycle de vie horodaté verrouillé par la state machine
 * (VTC-04) — chaque transition est journalisée dans `vtc_ride_events`.
 * Isolation tenant : trait BelongsToCompany (fail-closed).
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int|null $passenger_user_id
 * @property string|null $passenger_name
 * @property string|null $passenger_phone
 * @property float $pickup_latitude
 * @property float $pickup_longitude
 * @property string|null $pickup_address
 * @property float $dropoff_latitude
 * @property float $dropoff_longitude
 * @property string|null $dropoff_address
 * @property VtcRideStatus $status
 * @property int|null $fare_profile_id
 * @property int|null $estimated_distance_m
 * @property int|null $estimated_duration_s
 * @property int|null $estimated_price_minor
 * @property int|null $final_price_minor
 * @property string $currency
 * @property int|null $driver_id
 * @property string|null $cancel_reason
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $requested_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $arrived_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $expired_at
 */
final class VtcRide extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<VtcRideFactory> */
    use HasFactory;

    protected $table = 'vtc_rides';

    protected $fillable = [
        'reference',
        'passenger_user_id',
        'passenger_name',
        'passenger_phone',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'dropoff_address',
        'status',
        'fare_profile_id',
        'estimated_distance_m',
        'estimated_duration_s',
        'estimated_price_minor',
        'final_price_minor',
        'currency',
        'driver_id',
        'requested_at',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'expired_at',
        'cancel_reason',
        'idempotency_key',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'passenger_user_id' => 'integer',
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'dropoff_latitude' => 'float',
            'dropoff_longitude' => 'float',
            'status' => VtcRideStatus::class,
            'fare_profile_id' => 'integer',
            'estimated_distance_m' => 'integer',
            'estimated_duration_s' => 'integer',
            'estimated_price_minor' => 'integer',
            'final_price_minor' => 'integer',
            'driver_id' => 'integer',
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<VtcDriver, VtcRide> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(VtcDriver::class, 'driver_id');
    }

    /** @return BelongsTo<VtcFareProfile, VtcRide> */
    public function fareProfile(): BelongsTo
    {
        return $this->belongsTo(VtcFareProfile::class, 'fare_profile_id');
    }

    /** @return HasMany<VtcRideEvent> */
    public function events(): HasMany
    {
        return $this->hasMany(VtcRideEvent::class, 'ride_id');
    }
}
