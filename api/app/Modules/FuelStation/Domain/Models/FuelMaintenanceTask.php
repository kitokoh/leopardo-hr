<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5804 — Tâche de maintenance FuelStation (FUEL-010).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string|null $equipment_type
 * @property int|null $equipment_id
 * @property string $type
 * @property string $title
 * @property string|null $description
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_to
 * @property string|null $due_date
 * @property int|null $completed_by
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $completion_note
 *
 * @mixin Builder<static>
 */
class FuelMaintenanceTask extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_maintenance_tasks';

    protected $fillable = [
        'company_id',
        'station_id',
        'equipment_type',
        'equipment_id',
        'type',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to',
        'due_date',
        'completed_by',
        'completed_at',
        'completion_note',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
