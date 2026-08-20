<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Builder<static>
 */
class ZktecoDevice extends Model
{
    // ── Constantes de méthodes de pointage (#5120) ────────────────────
    /** @var string */
    public const PUNCH_METHOD_FINGERPRINT = 'fingerprint';

    /** @var string */
    public const PUNCH_METHOD_FACE = 'face';

    /** @var string */
    public const PUNCH_METHOD_CARD = 'card';

    /** @var list<string> */
    public const PUNCH_METHODS_ALL = [
        self::PUNCH_METHOD_FINGERPRINT,
        self::PUNCH_METHOD_FACE,
        self::PUNCH_METHOD_CARD,
    ];

    protected $fillable = [
        'company_id',
        'serial_number',
        'sync_token_hash',
        'name',
        'ip_address',
        'port',
        'protocol',
        'location_label',
        'status',
        'model',
        'firmware_version',
        'employee_capacity',
        'fingerprint_capacity',
        'face_capacity',
        'capabilities',
        'punch_methods',
        'last_heartbeat_at',
        'last_sync_at',
    ];

    /**
     * Le hash du token de sync ne doit JAMAIS sortir de l'API.
     */
    protected $hidden = [
        'sync_token_hash',
    ];

    protected $casts = [
        'port' => 'integer',
        'employee_capacity' => 'integer',
        'fingerprint_capacity' => 'integer',
        'face_capacity' => 'integer',
        'capabilities' => 'array',
        'punch_methods' => 'array',
        'last_heartbeat_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Retourne les méthodes de pointage autorisées pour ce device.
     *
     * Si `punch_methods` est null ou vide, toutes les méthodes sont
     * autorisées (rétro-compatibilité). Si le device ne définit aucune
     * méthode, on consulte le défaut entreprise (`kiosk.punch_methods.default`
     * dans `company_settings`) avant de retomber sur "toutes".
     *
     * @return list<string>
     */
    public function resolvedPunchMethods(): array
    {
        if (! empty($this->punch_methods)) {
            return array_values($this->punch_methods);
        }

        // Défaut entreprise (optionnel) — #5120 FR-001
        $company = $this->company;
        if ($company !== null) {
            $setting = \App\Core\Tenant\Domain\Models\CompanySetting::query()
                ->where('key', 'kiosk.punch_methods.default')
                ->first();

            if ($setting !== null && ! empty($setting->value)) {
                $decoded = json_decode((string) $setting->value, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    return array_values($decoded);
                }
            }
        }

        // Rétro-compat : toutes méthodes autorisées
        return self::PUNCH_METHODS_ALL;
    }

    /**
     * Vérifie si une méthode de pointage est autorisée par ce device.
     *
     * Délègue à `resolvedPunchMethods()` (device → défaut entreprise → toutes).
     */
    public function isPunchMethodAllowed(string $method): bool
    {
        return in_array($method, $this->resolvedPunchMethods(), true);
    }

    /**
     * Société propriétaire du dispositif (#4787).
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
