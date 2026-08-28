<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\CRM\Domain\Enums\CrmImportEntityType;
use App\Modules\CRM\Domain\Enums\CrmImportStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * #5714 — Session d'import CSV CRM (tenant-scoped).
 *
 * Une session matérialise le cycle preview → commit/cancel : le preview ne
 * touche JAMAIS les tables cibles (accounts/contacts/leads), le commit est
 * un acte explicite et idempotent (claim atomique de statut), l'annulation
 * est possible avant commit.
 *
 * @property int $id
 * @property string $company_id
 * @property CrmImportEntityType $entity_type
 * @property string $filename
 * @property CrmImportStatus $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $error_rows
 * @property array<mixed> $columns
 * @property array<mixed> $preview_data
 * @property array<mixed> $errors
 * @property array<mixed> $raw_rows
 * @property int|null $created_by
 * @property int|null $committed_by
 * @property int|null $cancelled_by
 * @property \Illuminate\Support\Carbon|null $committed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property array<mixed>|null $result
 *
 * @mixin Builder<static>
 */
class CrmImport extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_imports';

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
        'cancelled_by',
        'committed_at',
        'cancelled_at',
        'result',
    ];

    protected $casts = [
        'entity_type' => CrmImportEntityType::class,
        'status' => CrmImportStatus::class,
        'columns' => 'array',
        'preview_data' => 'array',
        'errors' => 'array',
        'raw_rows' => 'array',
        'result' => 'array',
        'committed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function isCommittable(): bool
    {
        return in_array($this->status->value, CrmImportStatus::committableStatuses(), true);
    }
}
