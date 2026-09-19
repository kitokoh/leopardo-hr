<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Service médical d'un établissement de santé — HC-002 (#7786, BC-30).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Les salles (health_rooms)
 * lui sont rattachées par FK composite (cross-tenant impossible).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthDepartment extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'health_departments';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
    ];

    /**
     * @return HasMany<HealthRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(HealthRoom::class, 'department_id');
    }
}
