<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Modules\FuelStation\Domain\Enums\FuelReadingAnomalyReason;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Relevé de compteur cumulé — Issue #5798 (FUEL-004).
 *
 * Append-only : une correction est une NOUVELLE ligne liée via
 * `corrects_reading_id` (jamais de UPDATE) — « correction versionnée et
 * auditée » (trait Auditable). L'idempotence est portée par
 * `UNIQUE (company_id, meter_id, read_at)`.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $meter_id
 * @property int|null $pump_id
 * @property int|null $site_id
 * @property int|null $station_id
 * @property int|null $operator_id
 * @property string|null $shift_ref
 * @property string $reading_value
 * @property Carbon $read_at
 * @property string|null $read_at_local
 * @property string|null $delta
 * @property bool $is_rollover
 * @property bool $is_anomaly
 * @property string|null $anomaly_reason
 * @property int|null $corrects_reading_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelMeterReading extends Model
{
    use Auditable;
    use BelongsToCompany;

    protected $table = 'fuel_meter_readings';

    protected $fillable = [
        'company_id',
        'meter_id',
        'pump_id',
        'site_id',
        'station_id',
        'operator_id',
        'shift_ref',
        'reading_value',
        'read_at',
        'read_at_local',
        'delta',
        'is_rollover',
        'is_anomaly',
        'anomaly_reason',
        'corrects_reading_id',
        'metadata',
    ];

    protected $casts = [
        'meter_id' => 'integer',
        'pump_id' => 'integer',
        'site_id' => 'integer',
        'station_id' => 'integer',
        'operator_id' => 'integer',
        'reading_value' => 'decimal:3',
        'read_at' => 'datetime',
        'delta' => 'decimal:3',
        'is_rollover' => 'boolean',
        'is_anomaly' => 'boolean',
        'corrects_reading_id' => 'integer',
        'metadata' => 'encrypted:array',
    ];

    /**
     * Dernier relevé (effectif) d'un compteur : le relevé le plus récent non
     * corrigé, ou la dernière correction.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->whereNull('corrects_reading_id');
    }

    /**
     * Détecte si la valeur décroît par rapport au relevé précédent du même
     * compteur (hors rollover explicite).
     */
    public function isDecreasing(): bool
    {
        $previous = static::query()
            ->where('meter_id', $this->meter_id)
            ->where('id', '!=', $this->id)
            ->where('read_at', '<=', $this->read_at)
            ->whereNull('corrects_reading_id')
            ->orderByDesc('read_at')
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return false;
        }

        return (float) $this->reading_value < (float) $previous->reading_value;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAnomalies(Builder $query): Builder
    {
        return $query->where('is_anomaly', true);
    }

    /**
     * @return FuelReadingAnomalyReason|null
     */
    public function anomalyReasonEnum(): ?FuelReadingAnomalyReason
    {
        return $this->anomaly_reason !== null
            ? FuelReadingAnomalyReason::tryFrom($this->anomaly_reason)
            : null;
    }
}
