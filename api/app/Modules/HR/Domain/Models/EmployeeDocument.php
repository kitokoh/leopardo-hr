<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Document du dossier employé (issue #5326 — gap G3 spec hr-lifecycle #5258).
 *
 * @property int $id
 * @property string $company_id
 * @property int $employee_id
 * @property string $type
 * @property string $status
 * @property Carbon|null $document_date
 * @property string|null $reference
 * @property string|null $url
 * @property string|null $notes
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 * @property-read Employee|null $uploader
 *
 * @mixin Builder<static>
 */
class EmployeeDocument extends Model
{
    use BelongsToCompany;
    /** @use HasFactory<EmployeeDocument> */
    use HasFactory;

    protected $table = 'employee_documents';

    public const TYPE_CONTRACT_SIGNED = 'contract_signed';

    public const TYPE_EMPLOYEE_FILE = 'employee_file';

    public const TYPE_CAREER_DECISION = 'career_decision';

    public const TYPE_DEPARTURE_RECORD = 'departure_record';

    public const TYPE_NOTICE_SUMMARY = 'notice_summary';

    public const TYPE_SETTLEMENT = 'settlement';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_OTHER = 'other';

    /** Types de documents reconnus par la checklist (spec §5). */
    public const TYPES = [
        self::TYPE_CONTRACT_SIGNED,
        self::TYPE_EMPLOYEE_FILE,
        self::TYPE_CAREER_DECISION,
        self::TYPE_DEPARTURE_RECORD,
        self::TYPE_NOTICE_SUMMARY,
        self::TYPE_SETTLEMENT,
        self::TYPE_CERTIFICATE,
        self::TYPE_OTHER,
    ];

    public const STATUS_RECEIVED = 'received';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_MISSING = 'missing';

    /** Statuts applicables à une ligne du registre. */
    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_UPLOADED,
        self::STATUS_GENERATED,
        self::STATUS_MISSING,
    ];

    protected $fillable = [
        'company_id',
        'employee_id',
        'type',
        'status',
        'document_date',
        'reference',
        'url',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }
}
