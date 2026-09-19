<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Salle d'un service médical — Issue #7786 (BC-30).
 *
 * Rattachée à un service (FK composite anti cross-tenant). Code unique par
 * tenant ; type et statut bornés (CHECK en base).
 *
 * @property int $id
 * @property string $company_id
 * @property int $department_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthRoom extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const TYPE_CONSULTATION = 'consultation';

    public const TYPE_HOSPITALIZATION = 'hospitalization';

    public const TYPE_OPERATING = 'operating';

    public const TYPE_EMERGENCY = 'emergency';

    public const TYPE_OTHER = 'other';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_CONSULTATION,
        self::TYPE_HOSPITALIZATION,
        self::TYPE_OPERATING,
        self::TYPE_EMERGENCY,
        self::TYPE_OTHER,
    ];

    protected $table = 'health_rooms';

    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'code',
        'type',
        'status',
    ];

    protected $casts = [
        'department_id' => 'integer',
        'type' => 'string',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<HealthDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(HealthDepartment::class, 'department_id');
    }

    /**
     * @return HasMany<HealthBed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(HealthBed::class, 'room_id');
    }
}
