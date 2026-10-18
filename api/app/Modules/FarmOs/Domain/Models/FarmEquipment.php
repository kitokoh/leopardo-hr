<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmEquipmentKind;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentMeterType;
use App\Modules\FarmOs\Domain\Enums\FarmEquipmentStatus;
use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.2 Materiel agricole.
 *
 * Honnetete des donnees (exigence de la mission) :
 *   - `meter_type` peut valoir `none` : un outil sans compteur n'est pas un
 *     outil incomplet, et `meter_value` reste alors `null` — Farm OS n'affiche
 *     jamais un compteur invente a zero ;
 *   - `meter_source` n'a que `manual` / `imported` : aucune valeur n'est
 *     presentee comme automatiquement mesuree (pas de telemetrie integree) ;
 *   - `status` est DECLARE par l'exploitant : Farm OS ne deduit jamais un etat
 *     mecanique d'un compteur.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_site_id
 * @property string $name
 * @property string|null $reference
 * @property string $kind
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property string|null $plate_number
 * @property Carbon|null $acquired_on
 * @property string|null $acquisition_cost
 * @property string|null $currency
 * @property string $meter_type
 * @property string|null $meter_value
 * @property string|null $meter_source
 * @property string $status
 * @property int|null $assigned_team_id
 * @property int|null $assigned_employee_id
 * @property string|null $notes
 * @property string|null $client_equipment_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmEquipment extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_equipment';

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'name',
        'reference',
        'kind',
        'brand',
        'model',
        'serial_number',
        'plate_number',
        'acquired_on',
        'acquisition_cost',
        'currency',
        'meter_type',
        'meter_value',
        'meter_source',
        'status',
        'assigned_team_id',
        'assigned_employee_id',
        'notes',
        'client_equipment_id',
        'created_by',
    ];

    protected $casts = [
        'acquired_on' => 'date',
        'acquisition_cost' => 'decimal:2',
        'meter_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kindEnum(): ?FarmEquipmentKind
    {
        return FarmEquipmentKind::tryFrom($this->kind);
    }

    public function statusEnum(): FarmEquipmentStatus
    {
        return FarmEquipmentStatus::tryFrom($this->status) ?? FarmEquipmentStatus::Active;
    }

    public function meterTypeEnum(): FarmEquipmentMeterType
    {
        return FarmEquipmentMeterType::tryFrom($this->meter_type) ?? FarmEquipmentMeterType::None;
    }

    public function meterSourceEnum(): ?FarmMeterSource
    {
        return $this->meter_source === null ? null : FarmMeterSource::tryFrom($this->meter_source);
    }

    /**
     * Le materiel suit-il un compteur ? Si non, `meter_value` doit rester
     * `null` : on n'affiche pas un compteur a zero pour un outil qui n'en a pas.
     */
    public function tracksMeter(): bool
    {
        return $this->meterTypeEnum()->isTracked();
    }

    /**
     * @return HasMany<FarmEquipmentUsageLog, $this>
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(FarmEquipmentUsageLog::class, 'farm_equipment_id');
    }

    /**
     * @return HasMany<FarmEquipmentMaintenance, $this>
     */
    public function maintenances(): HasMany
    {
        return $this->hasMany(FarmEquipmentMaintenance::class, 'farm_equipment_id');
    }

    /**
     * @return HasMany<FarmEquipmentBreakdown, $this>
     */
    public function breakdowns(): HasMany
    {
        return $this->hasMany(FarmEquipmentBreakdown::class, 'farm_equipment_id');
    }

    /**
     * @return HasMany<FarmEquipmentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(FarmEquipmentDocument::class, 'farm_equipment_id');
    }
}
