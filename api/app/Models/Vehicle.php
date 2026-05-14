<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $plate_number
 * @property string $brand
 * @property string|null $model
 * @property int $year
 * @property string $type
 * @property string|null $vin
 * @property string $fuel_type
 * @property string $status
 * @property int $mileage
 * @property Carbon|null $insurance_expiry
 * @property Carbon|null $technical_control_expiry
 * @property string|null $traccar_device_id
 * @property string|null $traccar_unique_id
 * @property int|null $assigned_driver_id
 * @property int|null $assigned_site_id
 * @property array<mixed> $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Vehicle extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'plate_number',
        'brand',
        'model',
        'year',
        'type',
        'vin',
        'fuel_type',
        'status',
        'mileage',
        'insurance_expiry',
        'technical_control_expiry',
        'traccar_device_id',
        'traccar_unique_id',
        'assigned_driver_id',
        'assigned_site_id',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'mileage' => 'integer',
            'year' => 'integer',
            'insurance_expiry' => 'date',
            'technical_control_expiry' => 'date',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_driver_id');
    }

    /** @return HasMany<VehicleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    /** @return HasMany<VehicleTrip, $this> */
    public function trips(): HasMany
    {
        return $this->hasMany(VehicleTrip::class);
    }

    /** @return HasMany<VehicleAlert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(VehicleAlert::class);
    }

    /** @return HasMany<VehicleMaintenance, $this> */
    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }
}
