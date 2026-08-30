<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Matière enseignée — Issue #5819 (EDU-003).
 *
 * Tenant-scoped (`company_id`, schéma tenant). Code unique par tenant ;
 * rattachement facultatif à un campus (FK composite anti cross-tenant).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $campus_id
 * @property string $code
 * @property string $name
 * @property string $default_coefficient
 * @property string $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduSubject extends Model
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

    protected $table = 'edu_subjects';

    protected $fillable = [
        'company_id',
        'campus_id',
        'code',
        'name',
        'default_coefficient',
        'status',
        'created_by',
    ];

    protected $casts = [
        'campus_id' => 'integer',
        'default_coefficient' => 'string',
        'status' => 'string',
    ];
}
