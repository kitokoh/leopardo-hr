<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Événement détecté par la chaîne vidéo (issue #7427, BC-19).
 *
 * Un événement existe **seulement** s'il a été détecté côté chaîne vidéo
 * (webhook MediaMTX / détecteur de mouvement) : aucune ligne n'est fabriquée
 * par le serveur applicatif. Le `metadata` ne contient jamais de PII
 * (pas de visage, pas de plaque) — voir
 * `docs/GESTION_PROJET/CAMERAS_ALERTES_RETENTION_RGPD.md`.
 *
 * @property int $id
 * @property string $company_id
 * @property int $camera_id
 * @property string $type motion|person|vehicle|line_crossing|tamper
 * @property string $severity info|warning|high|critical
 * @property Carbon|null $detected_at
 * @property string|null $snapshot_path
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Camera|null $camera
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CameraAlert> $alerts
 *
 * @mixin Builder<static>
 */
class CameraEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'camera_events';

    public const TYPE_MOTION = 'motion';

    public const TYPE_PERSON = 'person';

    public const TYPE_VEHICLE = 'vehicle';

    public const TYPE_LINE_CROSSING = 'line_crossing';

    public const TYPE_TAMPER = 'tamper';

    public const TYPES = [
        self::TYPE_MOTION,
        self::TYPE_PERSON,
        self::TYPE_VEHICLE,
        self::TYPE_LINE_CROSSING,
        self::TYPE_TAMPER,
    ];

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITIES = [
        self::SEVERITY_INFO,
        self::SEVERITY_WARNING,
        self::SEVERITY_HIGH,
        self::SEVERITY_CRITICAL,
    ];

    protected $fillable = [
        'company_id',
        'camera_id',
        'type',
        'severity',
        'detected_at',
        'snapshot_path',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'camera_id' => 'integer',
            'detected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Camera, $this> */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class, 'camera_id');
    }

    /** @return HasMany<CameraAlert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(CameraAlert::class, 'camera_event_id');
    }

    /** @param Builder<static> $query */
    public function scopeForCamera(Builder $query, int $cameraId): void
    {
        $query->where('camera_id', $cameraId);
    }

    /** @param Builder<static> $query */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
