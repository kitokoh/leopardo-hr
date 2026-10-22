<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmBreakdownSeverity;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.2 Panne de materiel.
 *
 * La gravite est DECLAREE par l'exploitant : Farm OS ne diagnostique jamais une
 * panne et ne propose aucun diagnostic mecanique automatise.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_equipment_id
 * @property string $severity
 * @property string $title
 * @property string|null $description
 * @property Carbon $occurred_at
 * @property Carbon|null $resolved_at
 * @property string|null $downtime_hours
 * @property string|null $cost_amount
 * @property string|null $currency
 * @property string|null $notes
 * @property int|null $reported_by
 * @property string|null $client_breakdown_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmEquipmentBreakdown extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_equipment_breakdowns';

    protected $fillable = [
        'company_id',
        'farm_equipment_id',
        'severity',
        'title',
        'description',
        'occurred_at',
        'resolved_at',
        'downtime_hours',
        'cost_amount',
        'currency',
        'notes',
        'reported_by',
        'client_breakdown_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
        'downtime_hours' => 'decimal:2',
        'cost_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmEquipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(FarmEquipment::class, 'farm_equipment_id');
    }

    public function severityEnum(): FarmBreakdownSeverity
    {
        return FarmBreakdownSeverity::tryFrom($this->severity) ?? FarmBreakdownSeverity::Minor;
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
