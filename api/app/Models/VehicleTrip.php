<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
