<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Classe (année scolaire, campus optionnel) — EDU-003 (issue #5819).
 *
 * `campus_id` : FK vers edu_campuses GARDÉE au niveau migration (table
 * livrée par EDU-002) ; la validation d'appartenance au tenant reste
 * applicative dans les Requests/Policies.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $campus_id
 * @property int $academic_year_id
 * @property string $code
 * @property string $name
 * @property string|null $grade_level
 * @property int|null $capacity
 * @property string $status active|archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduClass extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_classes';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id',
        'campus_id',
        'academic_year_id',
        'code',
        'name',
        'grade_level',
        'capacity',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'campus_id' => 'integer',
            'academic_year_id' => 'integer',
            'capacity' => 'integer',
        ];
    }
}
