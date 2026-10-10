<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Position horodatée d'un chauffeur (BC-34 VTC, VTC-02/#8358).
 *
 * Donnée personnelle (RGPD) : rétention bornée `vtc.positions_retention_days`
 * (défaut 30 j) + purge planifiée `vtc:purge-positions` (VTC-06). Ingestion
 * idempotente : contrainte UNIQUE(company_id, driver_id, recorded_at) — un
 * rejeu ne duplique jamais (VTC-05). Isolation tenant : BelongsToCompany.
 *
 * @property int $id
 * @property string $company_id
 * @property int $driver_id
 * @property float $latitude
 * @property float $longitude
 * @property \Illuminate\Support\Carbon|null $recorded_at
 * @property string $source
 */
final class VtcDriverPosition extends Model
{
    use BelongsToCompany;

    protected $table = 'vtc_driver_positions';

    protected $fillable = [
        'driver_id',
        'latitude',
        'longitude',
        'recorded_at',
        'source',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'driver_id' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VtcDriver, VtcDriverPosition> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(VtcDriver::class, 'driver_id');
    }
}
