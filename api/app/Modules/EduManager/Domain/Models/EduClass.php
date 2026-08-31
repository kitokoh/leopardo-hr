<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Classe d'un établissement scolaire — Issue #5819 (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Rattachée à une année
 * scolaire via la FK composite (academic_year_id, company_id) : une classe
 * ne peut pas référencer l'année d'un autre tenant (violation FK).
 *
 * @property int $id
 * @property string $company_id
 * @property int $academic_year_id
 * @property string $name
 * @property string|null $grade_level
 * @property int|null $capacity
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduAcademicYear $academicYear
 *
 * @mixin Builder<static>
 */
class EduClass extends Model
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

    protected $table = 'edu_classes';

    protected $fillable = [
        'company_id',
        'academic_year_id',
        'name',
        'grade_level',
        'capacity',
        'status',
    ];

    protected $casts = [
        'academic_year_id' => 'integer',
        'capacity' => 'integer',
        'status' => 'string',
    ];

    /**
     * Année scolaire de rattachement.
     *
     * @return BelongsTo<EduAcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(EduAcademicYear::class, 'academic_year_id');
    }
}
