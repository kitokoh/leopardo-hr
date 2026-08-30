<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Année scolaire — EDU-003 (issue #5819).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $status active|archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduAcademicYear extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_academic_years';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'start_date',
        'end_date',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
