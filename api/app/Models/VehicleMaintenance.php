<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $vehicle_id
 * @property int|null $company_id
 * @property string $type
 * @property string $description
 * @property string $cost
 * @property string $currency
 * @property int $mileage_at_service
 * @property \Illuminate\Support\Carbon $service_date
 * @property \Illuminate\Support\Carbon $next_service_date
 * @property int $next_service_mileage
 * @property string $provider
 * @property string|null $invoice_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
