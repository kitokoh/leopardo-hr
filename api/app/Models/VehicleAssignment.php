<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $vehicle_id
 * @property int|null $employee_id
 * @property int|null $company_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $reason
 * @property string|null $created_by
 * @property Carbon|null $created_at
 */
class VehicleAssignment extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'employee_id',
        'company_id',
        'start_date',
        'end_date',
        'reason',
        'created_by',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
