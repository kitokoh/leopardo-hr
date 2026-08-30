<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Enseignant = employé du tenant (lien HR) — EDU-003 (issue #5819).
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 *
 * @mixin Builder<static>
 */
class EduTeacher extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_teachers';

    protected $fillable = [
        'company_id',
        'employee_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
