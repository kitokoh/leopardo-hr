<?php

namespace App\Modules\Cameras\Domain;

use App\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Journal d'accès caméra — alimenté à chaque action sensible (view, verify,
 * token_verify_denied, share, revoke). Section 6.1 (endpoint access-logs).
 *
 * Table write-only côté application : aucun update ni delete prévu.
 * Rétention gérée en Phase 2 par un job planifié.
 *
 * @property int $id
 * @property int $company_id
 * @property int $camera_id
 * @property int|null $employee_id
 * @property int|null $access_token_id
 * @property string $actor_type
 * @property string $action
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read Camera|null $camera
 * @property-read Employee|null $employee
 * @property-read CameraAccessToken|null $accessToken
 */
class CameraAccessLog extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'created_at';

    public const ACTOR_EMPLOYEE = 'employee';

    public const ACTOR_EXTERNAL_TOKEN = 'external_token';

    public const ACTOR_SYSTEM = 'system';

    protected $table = 'camera_access_logs';

    public $timestamps = true;

    protected $fillable = [
        'company_id',
        'camera_id',
        'employee_id',
        'access_token_id',
        'actor_type',
        'action',
        'reason',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Camera, $this> */
    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class, 'camera_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<CameraAccessToken, $this> */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(CameraAccessToken::class, 'access_token_id');
    }
}
