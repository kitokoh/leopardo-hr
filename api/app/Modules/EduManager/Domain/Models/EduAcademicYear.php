<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Année scolaire d'un établissement — Issue #5819 (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Période cohérente garantie
 * par le CHECK `start_date < end_date` ; nom unique par tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduAcademicYear extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $table = 'edu_academic_years';

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => 'string',
    ];

    /**
     * @return HasMany<EduClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(EduClass::class, 'academic_year_id');
    }
}
