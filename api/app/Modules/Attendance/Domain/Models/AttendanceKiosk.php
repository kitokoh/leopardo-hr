<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanySetting;
use App\Modules\Attendance\Domain\Enums\VerificationMethod;
use App\Shared\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Kiosque de pointage (BIO-005 #6766, BIO-006 #6767).
 *
 * Identité cryptographique révocable : `device_code` (hash déterministe,
 * #5588) + `sync_token_hash` (renouvelable). Un kiosque révoqué
 * (`status = revoked`) ne peut plus pointer ni synchroniser. Le `site_id`
 * est fixé au provisioning — un kiosque ne peut pas changer de tenant ou de
 * site par simple modification de payload.
 *
 * @property int $id
 * @property string $company_id
 * @property Company $company
 * @property string $name
 * @property string|null $location_label
 * @property int|null $site_id
 * @property string $device_code
 * @property string $status
 * @property string $biometric_mode
 * @property array<string>|null $punch_methods
 * @property string|null $trusted_device_label
 * @property string|null $sync_token_hash
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_sync_at
 * @property int $acked_event_counter
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_employee_id
 *
 * @mixin Builder<static>
 */
class AttendanceKiosk extends Model
{
    use BelongsToCompany;

    /** Méthodes de pointage pilotables sur un kiosque (BIO-006). */
    public const KIOSK_PUNCH_METHODS_ALL = [
        VerificationMethod::Fingerprint->value,
        VerificationMethod::Face->value,
        VerificationMethod::Badge->value,
        VerificationMethod::Pin->value,
        VerificationMethod::Manager->value,
    ];

    /**
     * Hache un device_code pour le stockage/lookup (issue #5588).
     *
     * Le device_code voyage dans l'URL (`/kiosks/{deviceCode}/...`) : il
     * doit rester une clé de lookup déterministe, mais ne doit plus être
     * stocké en clair. `sha256(MAJUSCULES)` : indexé par égalité après
     * hachage de l'entrée, irréversible au repos (un dump DB n'expose plus
     * les codes). Entropie d'origine (Str::random(10) uppercase) — aucun
     * salt nécessaire (pas de rainbow table sur 36^10).
     */
    public static function hashDeviceCode(string $code): string
    {
        return hash('sha256', mb_strtoupper($code));
    }

    protected $fillable = [
        'company_id',
        'name',
        'location_label',
        'site_id',
        'device_code',
        'status',
        'biometric_mode',
        'punch_methods',
        'trusted_device_label',
        'sync_token_hash',
        'last_seen_at',
        'last_sync_at',
        'acked_event_counter',
        'revoked_at',
        'revoked_by_employee_id',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'acked_event_counter' => 'integer',
        'revoked_at' => 'datetime',
        'punch_methods' => 'array',
    ];

    public function isRevoked(): bool
    {
        return $this->status === 'revoked' || $this->revoked_at !== null;
    }

    /**
     * Matrice des méthodes de pointage activées (BIO-006).
     *
     * Règle : punch_methods du kiosque → défaut entreprise
     * (`kiosk.punch_methods.default` dans company_settings) → toutes.
     * `card` (vocabulaire ZKTeco) est normalisé en `badge` (domaine ATT-002).
     *
     * @return list<string>
     */
    public function resolvedPunchMethods(): array
    {
        $configured = $this->normalizeMethods($this->punch_methods ?? []);
        if ($configured !== []) {
            return $configured;
        }

        // La relation `company` est posée manuellement (PlatformCompanyLookup) :
        // on ne consulte le défaut entreprise que lorsqu'elle est chargée.
        $company = $this->relationLoaded('company') ? $this->company : null;
        if ($company !== null) {
            $setting = CompanySetting::query()
                ->where('key', 'kiosk.punch_methods.default')
                ->first();

            if ($setting !== null && ! empty($setting->value)) {
                $decoded = json_decode((string) $setting->value, true);
                if (is_array($decoded)) {
                    $configured = $this->normalizeMethods($decoded);
                    if ($configured !== []) {
                        return $configured;
                    }
                }
            }
        }

        return self::KIOSK_PUNCH_METHODS_ALL;
    }

    /**
     * Vérifie si une méthode de pointage est activée sur ce kiosque.
     *
     * @param  string  $method  valeur de domaine VerificationMethod
     */
    public function isPunchMethodAllowed(string $method): bool
    {
        return in_array($method, $this->resolvedPunchMethods(), true);
    }

    /**
     * @param  array<array-key, mixed>  $methods
     * @return list<string>
     */
    private function normalizeMethods(array $methods): array
    {
        $normalized = [];
        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }
            $normalized[] = $method === 'card' ? VerificationMethod::Badge->value : $method;
        }

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $method): bool => in_array($method, self::KIOSK_PUNCH_METHODS_ALL, true)
        )));
    }
}
