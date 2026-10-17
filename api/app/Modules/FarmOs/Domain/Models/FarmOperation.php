<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Enums\FarmOperationType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Opération agricole (intervention planifiée ou réalisée).
 *
 * Cœur du calendrier agricole : planifiable, affectable à un responsable, suivie
 * (started_at) puis clôturable (finished_at). Le statut suit une machine à
 * états fermée ({@see FarmOperationStatus}).
 *
 * Idempotence terrain : `client_operation_id` est UNIQUE par tenant — le rejeu
 * d'une écriture mobile ou hors ligne renvoie l'opération existante au lieu d'en
 * créer une seconde.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_site_id
 * @property int|null $parcel_id
 * @property int|null $crop_cycle_id
 * @property string $op_type
 * @property string $title
 * @property string|null $instructions
 * @property string $status
 * @property string $priority
 * @property Carbon|null $planned_start_at
 * @property Carbon|null $planned_end_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $assigned_to
 * @property int|null $farm_team_id
 * @property int|null $responsible_id
 * @property string|null $quantity_value
 * @property string|null $quantity_unit
 * @property string|null $progress_percent
 * @property string|null $notes
 * @property string|null $client_operation_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmOperation extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_operations';

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'parcel_id',
        'crop_cycle_id',
        'op_type',
        'title',
        'instructions',
        'status',
        'priority',
        'planned_start_at',
        'planned_end_at',
        'started_at',
        'finished_at',
        'assigned_to',
        'farm_team_id',
        'responsible_id',
        'quantity_value',
        'quantity_unit',
        'progress_percent',
        'notes',
        'client_operation_id',
        'created_by',
    ];

    protected $casts = [
        'planned_start_at' => 'datetime',
        'planned_end_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Parcel, $this>
     */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class, 'parcel_id');
    }

    /**
     * @return BelongsTo<FarmSite, $this>
     */
    public function farmSite(): BelongsTo
    {
        return $this->belongsTo(FarmSite::class, 'farm_site_id');
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class, 'crop_cycle_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_id');
    }

    public function statusEnum(): FarmOperationStatus
    {
        return FarmOperationStatus::tryFrom($this->status) ?? FarmOperationStatus::Planned;
    }

    public function typeEnum(): ?FarmOperationType
    {
        return FarmOperationType::tryFrom($this->op_type);
    }

    public function isMutable(): bool
    {
        return ! $this->statusEnum()->isTerminal();
    }
}
