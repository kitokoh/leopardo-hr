<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de bulletin (moyenne par matière) — Issue #5824 (EDU-008).
 *
 * Read model recalculable : régénéré à chaque `generate()` du bulletin.
 *
 * @property int $id
 * @property string $company_id
 * @property int $report_card_id
 * @property int $subject_id
 * @property string $average
 * @property string $coefficient
 * @property int $assessment_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduReportCardLine extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_report_card_lines';

    protected $fillable = [
        'company_id',
        'report_card_id',
        'subject_id',
        'average',
        'coefficient',
        'assessment_count',
    ];

    protected $casts = [
        'report_card_id' => 'integer',
        'subject_id' => 'integer',
        'average' => 'string',
        'coefficient' => 'string',
        'assessment_count' => 'integer',
    ];

    /**
     * @return BelongsTo<EduReportCard, $this>
     */
    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(EduReportCard::class, 'report_card_id');
    }

    /**
     * @return BelongsTo<EduSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(EduSubject::class, 'subject_id');
    }
}
