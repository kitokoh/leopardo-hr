<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Audit biométrique tenant-scoped (BIO-008, #6773).
 *
 * Ne contient JAMAIS de photo, gabarit ou secret : uniquement des ids, des
 * codes et une corrélation. Les payloads biométriques sont rédigés à la
 * source (BiometricAuditLogger n'accepte aucun contenu).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $employee_id
 * @property int|null $kiosk_id
 * @property int|null $site_id
 * @property int|null $actor_employee_id
 * @property string $event
 * @property string|null $method
 * @property string|null $result_code
 * @property string|null $correlation_id
 * @property string|null $device_code_hash
 * @property array<string, mixed>|null $context
 * @property Carbon $occurred_at
 *
 * @mixin Builder<static>
 */
class BiometricAuditLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'kiosk_id',
        'site_id',
        'actor_employee_id',
        'event',
        'method',
        'result_code',
        'correlation_id',
        'device_code_hash',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];
}
