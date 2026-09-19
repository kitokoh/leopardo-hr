<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Modules\CRM\Domain\Enums\CrmExportEntity;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Job d'export CRM (issue #5729).
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $user_id
 * @property string $entity
 * @property string $format
 * @property array<string, mixed>|null $filters
 * @property array<int, string>|null $columns
 * @property string $status
 * @property int $progress
 * @property string|null $file_path
 * @property string|null $file_name
 * @property Carbon|null $expires_at
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmExportJob extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'crm_export_jobs';

    // #7646 — allowlist explicite SANS `company_id` : le tenant est posé par
    // le hook creating de BelongsToCompany, jamais par mass assignment.
    protected $fillable = [
        'user_id',
        'entity',
        'format',
        'filters',
        'columns',
        'status',
        'progress',
        'file_path',
        'file_name',
        'expires_at',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'progress' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function isValidEntity(string $entity): bool
    {
        return CrmExportEntity::isValid($entity);
    }
}
