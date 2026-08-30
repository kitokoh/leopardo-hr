<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Bulletin scolaire — Issue #5824 (EDU-008).
 *
 * Tenant-scoped. UNIQUE (student_id, academic_year_id, period) par tenant.
 * Cycle de vie : draft → validated → published (validé par la direction,
 * publié pour les guardians autorisés).
 *
 * @property int $id
 * @property string $company_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property string $period
 * @property string $status
 * @property Carbon|null $generated_at
 * @property Carbon|null $validated_at
 * @property int|null $validated_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduReportCard extends Model
{
    use BelongsToCompany;

    public const PERIOD_TERM1 = 'term1';

    public const PERIOD_TERM2 = 'term2';

    public const PERIOD_TERM3 = 'term3';

    public const PERIOD_FINAL = 'final';

    public const PERIODS = [
        self::PERIOD_TERM1,
        self::PERIOD_TERM2,
        self::PERIOD_TERM3,
        self::PERIOD_FINAL,
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_VALIDATED,
        self::STATUS_PUBLISHED,
    ];

    protected $table = 'edu_report_cards';

    protected $fillable = [
        'company_id',
        'student_id',
        'academic_year_id',
        'period',
        'status',
        'generated_at',
        'validated_at',
        'validated_by',
        'published_at',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'academic_year_id' => 'integer',
        'period' => 'string',
        'status' => 'string',
        'generated_at' => 'datetime',
        'validated_at' => 'datetime',
        'validated_by' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    /**
     * @return HasMany<EduReportCardLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(EduReportCardLine::class, 'report_card_id');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
