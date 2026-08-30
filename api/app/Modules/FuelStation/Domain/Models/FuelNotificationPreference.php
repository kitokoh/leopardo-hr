<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Préférence de notification FuelStation — FUEL-019 (#5813).
 *
 * Un canal (in_app|email|push) est activable/désactivable par type
 * d'événement et par station (`station_id` NULL = toutes les stations du
 * tenant). Absence de ligne = canal in_app activé par défaut.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $event_type
 * @property string $channel in_app|email|push
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelNotificationPreference extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_notification_preferences';

    public const EVENT_READING_ANOMALY = 'reading_anomaly';

    public const EVENT_STOCK_VARIANCE = 'stock_variance';

    public const EVENT_MISSING_CLOSE = 'missing_cash_session_close';

    public const EVENT_MAINTENANCE_DUE = 'maintenance_due';

    public const EVENT_INCIDENT = 'incident';

    public const EVENT_TYPES = [
        self::EVENT_READING_ANOMALY,
        self::EVENT_STOCK_VARIANCE,
        self::EVENT_MISSING_CLOSE,
        self::EVENT_MAINTENANCE_DUE,
        self::EVENT_INCIDENT,
    ];

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_PUSH = 'push';

    public const CHANNELS = [self::CHANNEL_IN_APP, self::CHANNEL_EMAIL, self::CHANNEL_PUSH];

    protected $fillable = [
        'company_id',
        'station_id',
        'event_type',
        'channel',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
