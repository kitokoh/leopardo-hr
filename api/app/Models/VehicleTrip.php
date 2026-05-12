<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $vehicle_id
 * @property int|null $company_id
 * @property int|null $driver_id
 * @property \Illuminate\Support\Carbon $start_time
 * @property \Illuminate\Support\Carbon $end_time
 * @property mixed $start_lat
 * @property mixed $start_lng
 * @property string|null $start_address
 * @property mixed $end_lat
 * @property mixed $end_lng
 * @property string|null $end_address
 * @property string $distance_km
 * @property int $duration_minutes
 * @property string $max_speed_kmh
 * @property string $avg_speed_kmh
 * @property string $fuel_consumed
 * @property int|null $traccar_trip_id
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class VehicleTrip extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'driver_id',
        'start_time',
        'end_time',
        'start_lat',
        'start_lng',
        'start_address',
        'end_lat',
        'end_lng',
        'end_address',
        'distance_km',
        'duration_minutes',
        'max_speed_kmh',
        'avg_speed_kmh',
        'fuel_consumed',
        'traccar_trip_id',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'start_lat' => 'decimal:7',
            'start_lng' => 'decimal:7',
            'end_lat' => 'decimal:7',
            'end_lng' => 'decimal:7',
            'distance_km' => 'decimal:2',
            'duration_minutes' => 'integer',
            'max_speed_kmh' => 'decimal:2',
            'avg_speed_kmh' => 'decimal:2',
            'fuel_consumed' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'driver_id');
    }
}
