<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $company_id
 * @property \App\Core\Tenant\Domain\Models\Company $company
 * @property string $name
 * @property string|null $location_label
 * @property string $device_code
 * @property string $status
 * @property string $biometric_mode
 * @property string|null $trusted_device_label
 * @property string|null $sync_token_hash
 * @property \Carbon\Carbon|null $last_seen_at
 * @property \Carbon\Carbon|null $last_sync_at
 *
 * @mixin Builder<static>
 */
class AttendanceKiosk extends Model
{
    use BelongsToCompany;

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
        'device_code',
        'status',
        'biometric_mode',
        'trusted_device_label',
        'sync_token_hash',
        'last_seen_at',
        'last_sync_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];
}
