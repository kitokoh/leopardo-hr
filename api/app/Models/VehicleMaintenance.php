<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    use BelongsToCompany;

    protected $table = 'vehicle_maintenances';

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'type',
        'description',
        'cost',
        'currency',
        'mileage_at_service',
        'service_date',
        'next_service_date',
        'next_service_mileage',
        'provider',
        'invoice_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'mileage_at_service' => 'integer',
            'service_date' => 'date',
            'next_service_date' => 'date',
            'next_service_mileage' => 'integer',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
