<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bulletin de période d'un élève — Issue #5824 (EDU-008).
 *
 * Snapshot REPRODUCTIBLE : `data` (jsonb) est une pure fonction des notes
 * publiées au moment de la génération (moyennes par matière, aucun
 * horodatage) — régénérer un brouillon donne le même résultat tant que les
 * notes n'ont pas changé. `average_score` porte la moyenne globale
 * (numeric(6,2)). Cycle de vie borné en base (CHECK) :
 * draft → validated → published → archived ; un bulletin non-draft est
 * IMMUABLE (service ReportCardService).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) : `data`
 * ne contient que des moyennes par matière — aucune donnée nominative,
 * jamais exposée hors tenant (RBAC EduReportCardPolicy).
 *
 * @property int $id
 * @property string $company_id
 * @property int $student_id
 * @property int $class_id
 * @property int $academic_year_id
 * @property string $period_label
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property array<string, mixed> $data
 * @property string|null $average_score
 * @property string $status
 * @property int|null $validated_by
 * @property Carbon|null $validated_at
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduStudent $student
 * @property-read EduClass $class
 *
 * @mixin Builder<static>
 */
class EduReportCard extends Model
{
    use BelongsToCompany;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_VALIDATED,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'edu_report_cards';

    protected $fillable = [
        'company_id',
        'student_id',
        'class_id',
        'academic_year_id',
        'period_label',
        'period_start',
        'period_end',
        'data',
        'average_score',
        'status',
        'validated_by',
        'validated_at',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'class_id' => 'integer',
        'academic_year_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        // Snapshot des moyennes par matière — reproductible.
        'data' => 'array',
        'average_score' => 'decimal:2',
        'status' => 'string',
        'validated_by' => 'integer',
        'validated_at' => 'datetime',
        'published_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * Élève du bulletin.
     *
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    /**
     * Classe du bulletin (celle de l'élève sur la période).
     *
     * @return BelongsTo<EduClass, $this>
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(EduClass::class, 'class_id');
    }
}
