<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Session d'import CSV EduManager — Issue #5833 (EDU-017).
 *
 * Cycle de vie : previewed → committed | cancelled | failed. Les lignes
 * brutes sont conservées en JSONB (rollback logique possible — re-commit
 * sans écriture destructive).
 *
 * @property int $id
 * @property string $company_id
 * @property string $entity_type
 * @property string $filename
 * @property string $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $error_rows
 * @property array<int, string>|null $columns
 * @property array<int, array<string, mixed>>|null $preview_data
 * @property array<int, array<string, mixed>>|null $errors
 * @property array<int, array<string, mixed>>|null $raw_rows
 * @property int|null $created_by
 * @property int|null $committed_by
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduImport extends Model
{
    use BelongsToCompany;

    public const ENTITY_STUDENTS = 'students';

    public const ENTITY_GUARDIANS = 'guardians';

    public const ENTITY_CLASSES = 'classes';

    public const ENTITY_SUBJECTS = 'subjects';

    public const ENTITY_GRADES = 'grades';

    public const ENTITIES = [
        self::ENTITY_STUDENTS,
        self::ENTITY_GUARDIANS,
        self::ENTITY_CLASSES,
        self::ENTITY_SUBJECTS,
        self::ENTITY_GRADES,
    ];

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const TERMINAL_STATUSES = [
        self::STATUS_COMMITTED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'edu_imports';

    protected $fillable = [
        'company_id',
        'entity_type',
        'filename',
        'status',
        'total_rows',
        'valid_rows',
        'error_rows',
        'columns',
        'preview_data',
        'errors',
        'raw_rows',
        'created_by',
        'committed_by',
        'committed_at',
    ];

    protected $casts = [
        'entity_type' => 'string',
        'status' => 'string',
        'total_rows' => 'integer',
        'valid_rows' => 'integer',
        'error_rows' => 'integer',
        'columns' => 'array',
        'preview_data' => 'array',
        'errors' => 'array',
        'raw_rows' => 'array',
        'committed_by' => 'integer',
        'committed_at' => 'datetime',
    ];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
