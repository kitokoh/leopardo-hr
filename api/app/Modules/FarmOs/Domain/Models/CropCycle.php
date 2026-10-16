<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — Cycle cultural (historique des cultures d'une parcelle).
 *
 * Porte les dates de semis/plantation et de récolte (prévues et réelles) ainsi
 * que les rendements. Un cycle `harvested` ou `failed` est clos : sa
 * modification passe par une action dédiée auditée.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $parcel_id
 * @property string $crop_code
 * @property string|null $crop_variety
 * @property string|null $season_label
 * @property Carbon|null $planned_sowing_on
 * @property Carbon|null $actual_sowing_on
 * @property Carbon|null $planned_harvest_on
 * @property Carbon|null $actual_harvest_on
 * @property string|null $expected_yield_value
 * @property string|null $expected_yield_unit
 * @property string|null $actual_yield_value
 * @property string|null $actual_yield_unit
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CropCycle extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_crop_cycles';

    public const STATUSES = ['planned', 'growing', 'harvested', 'failed', 'cancelled'];

    /** Statuts clos : plus aucune modification directe. */
    public const CLOSED_STATUSES = ['harvested', 'failed', 'cancelled'];

    protected $fillable = [
        'company_id',
        'parcel_id',
        'crop_code',
        'crop_variety',
        'season_label',
        'planned_sowing_on',
        'actual_sowing_on',
        'planned_harvest_on',
        'actual_harvest_on',
        'expected_yield_value',
        'expected_yield_unit',
        'actual_yield_value',
        'actual_yield_unit',
        'status',
        'notes',
    ];

    protected $casts = [
        'planned_sowing_on' => 'date',
        'actual_sowing_on' => 'date',
        'planned_harvest_on' => 'date',
        'actual_harvest_on' => 'date',
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
     * @return HasMany<FarmOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(FarmOperation::class, 'crop_cycle_id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }
}
