<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5804 — Incident équipement FuelStation (FUEL-010).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string|null $equipment_type
 * @property int|null $equipment_id
 * @property string $title
 * @property string|null $description
 * @property string $severity
 * @property string $status
 * @property int|null $assigned_to
 * @property int $reported_by
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property string|null $resolution_note
 * @property array<mixed>|null $metadata
 *
 * @mixin Builder<static>
 */
class FuelIncident extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_incidents';

    protected $fillable = [
        'company_id',
        'station_id',
        'equipment_type',
        'equipment_id',
        'title',
        'description',
        'severity',
        'status',
        'assigned_to',
        'reported_by',
        'resolved_by',
        'resolved_at',
        'resolution_note',
        'metadata',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }
}
