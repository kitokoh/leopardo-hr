<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMaintenanceType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.2 Entretien de materiel (planifie ou realise).
 *
 * `cost_amount` est NULLABLE : un cout inconnu reste inconnu et n'est jamais
 * remplace par zero — la rentabilite distingue explicitement reel / estime /
 * manquant.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_equipment_id
 * @property string $maintenance_type
 * @property string $status
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $planned_at
 * @property Carbon|null $performed_at
 * @property string|null $meter_value_at
 * @property string|null $cost_amount
 * @property string|null $currency
 * @property int|null $supplier_id
 * @property int|null $breakdown_id
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_maintenance_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmEquipmentMaintenance extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_equipment_maintenances';

    protected $fillable = [
        'company_id',
        'farm_equipment_id',
        'maintenance_type',
        'status',
        'title',
        'description',
        'planned_at',
        'performed_at',
        'meter_value_at',
        'cost_amount',
        'currency',
        'supplier_id',
        'breakdown_id',
        'notes',
        'recorded_by',
        'client_maintenance_id',
    ];

    protected $casts = [
        'planned_at' => 'datetime',
        'performed_at' => 'datetime',
        'meter_value_at' => 'decimal:2',
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

    public function typeEnum(): ?FarmMaintenanceType
    {
        return FarmMaintenanceType::tryFrom($this->maintenance_type);
    }

    public function statusEnum(): FarmMaintenanceStatus
    {
        return FarmMaintenanceStatus::tryFrom($this->status) ?? FarmMaintenanceStatus::Planned;
    }

    public function isOpen(): bool
    {
        return ! $this->statusEnum()->isTerminal();
    }
}
