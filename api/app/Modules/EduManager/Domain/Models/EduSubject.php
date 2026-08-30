<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Matière scolaire — EDU-003 (issue #5819).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 *
 * @mixin Builder<static>
 */
class EduSubject extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_subjects';

    protected $fillable = [
        'company_id',
        'code',
        'name',
    ];
}
