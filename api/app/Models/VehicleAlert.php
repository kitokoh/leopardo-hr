<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $vehicle_id
 * @property int|null $company_id
 * @property string $type
 * @property string|null $message
 * @property mixed $latitude
 * @property mixed $longitude
 * @property string $speed
 * @property bool $acknowledged
 * @property string|null $acknowledged_by
 * @property int|null $traccar_event_id
 * @property Carbon|null $created_at
 */
class VehicleAlert extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'type',
        'message',
        'latitude',
        'longitude',
        'speed',
        'acknowledged',
        'acknowledged_by',
        'traccar_event_id',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed' => 'decimal:2',
            'acknowledged' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acknowledged_by');
    }
}
