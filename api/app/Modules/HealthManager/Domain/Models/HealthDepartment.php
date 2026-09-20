<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Service médical d'un établissement de santé — Issue #7786 (BC-31).
 *
 * Code unique par tenant ; statut borné (active|inactive, CHECK en base).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $code
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

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $table = 'health_departments';

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * @return HasMany<HealthRoom, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(HealthRoom::class, 'department_id');
    }

    /**
     * @return HasMany<HealthPractitioner, $this>
     */
    public function practitioners(): HasMany
    {
        return $this->hasMany(HealthPractitioner::class, 'department_id');
    }
}
