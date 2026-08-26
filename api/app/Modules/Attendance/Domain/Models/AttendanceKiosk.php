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

    /**
     * Issue #5588 : le device_code n'est JAMAIS stocké en clair. SHA-256 hex
     * déterministe (64 chars) — queryable par la borne qui présente le code
     * dans l'URL (bcrypt ne l'est pas), même philosophie que les tokens
     * ZKTeco (sync_token_hash). La comparaison est insensible à la casse :
     * le code affiché par la borne est en MAJUSCULES.
     */
    public static function hashDeviceCode(string $deviceCode): string
    {
        return hash('sha256', strtoupper($deviceCode));
    }

    public function setDeviceCodeAttribute(string $value): void
    {
        $this->attributes['device_code'] = self::hashDeviceCode($value);
    }
}
