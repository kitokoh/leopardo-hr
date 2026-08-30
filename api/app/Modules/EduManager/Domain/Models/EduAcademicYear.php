<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Année scolaire d'un établissement — Issue #5819 (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Une période scolaire doit
 * rester cohérente : `start_date` < `end_date` est garanti en base par le
 * CHECK `edu_academic_years_period_check` (migration #5819-1).
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EduClass> $classes
 *
 * @mixin Builder<static>
 */
class EduAcademicYear extends Model
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

    protected $table = 'edu_academic_years';

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'string',
    ];

    /**
     * Classes rattachées à cette année scolaire.
     *
     * @return HasMany<EduClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(EduClass::class, 'academic_year_id');
    }
}
