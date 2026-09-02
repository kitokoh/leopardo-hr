<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal de déduplication des alertes FuelStation (FUEL-019, #5813).
 *
 * Une alerte notifiée est enregistrée avec une clé unique par tenant
 * (type + cible + date) : le rejeu du job ne re-notifie jamais la même
 * anomalie. L'audit des canaux reste dans `communication_events`.
 *
 * @property int $id
 * @property string $company_id
 * @property string $alert_type
 * @property string $alert_key
 * @property int|null $station_id
 * @property string|null $payload
 * @property int|null $notified_by
 * @property Carbon $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelAlertLog extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_alert_log';

    public const TYPE_METER_ANOMALY = 'meter_anomaly';

    public const TYPE_MISSING_CLOSURE = 'missing_closure';

    public const TYPE_STOCK_VARIANCE = 'stock_variance';

    public const TYPE_MAINTENANCE_DUE = 'maintenance_due';

    public const TYPES = [
        self::TYPE_METER_ANOMALY,
        self::TYPE_MISSING_CLOSURE,
        self::TYPE_STOCK_VARIANCE,
        self::TYPE_MAINTENANCE_DUE,
    ];

    protected $fillable = [
        'company_id',
        'alert_type',
        'alert_key',
        'station_id',
        'payload',
        'notified_by',
        'notified_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'notified_at' => 'datetime',
        ];
    }
}
