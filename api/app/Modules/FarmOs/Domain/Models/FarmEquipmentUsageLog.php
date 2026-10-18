<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmMeterSource;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.2 Releve de compteur (journal d'utilisation).
 *
 * JOURNAL IMMUABLE : chaque releve laisse une ligne, on ne met jamais a jour un
 * releve existant. `meter_delta` est NULL au premier releve — Farm OS ne suppose
 * pas un compteur a zero au depart.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_equipment_id
 * @property string $meter_value
 * @property string|null $meter_delta
 * @property string $meter_source
 * @property int|null $farm_operation_id
 * @property int|null $parcel_id
 * @property int|null $operator_id
 * @property string|null $reason
 * @property int|null $recorded_by
 * @property string|null $client_usage_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmEquipmentUsageLog extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_equipment_usage_logs';

    protected $fillable = [
        'company_id',
        'farm_equipment_id',
        'meter_value',
        'meter_delta',
        'meter_source',
        'farm_operation_id',
        'parcel_id',
        'operator_id',
        'reason',
        'recorded_by',
        'client_usage_id',
        'occurred_at',
    ];

    protected $casts = [
        'meter_value' => 'decimal:2',
        'meter_delta' => 'decimal:2',
        'occurred_at' => 'datetime',
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

    public function sourceEnum(): FarmMeterSource
    {
        return FarmMeterSource::tryFrom($this->meter_source) ?? FarmMeterSource::Manual;
    }
}
