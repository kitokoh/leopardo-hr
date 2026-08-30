<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dossier d'inscription (admission) — Issue #5820 (EDU-004).
 *
 * Le dossier précède l'élève : `AdmissionService::convert()` crée l'`EduStudent`
 * correspondant (idempotent, `student_id` renseigné, statut `enrolled`).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 * `applicant_name` nominative en clair (jamais hors tenant), `contact_reference`
 * chiffrée au repos (cast `encrypted`, PAS de FK — lien découplé du CRM client,
 * spec §6.4). `consent_marketing`/`consent_at` tracent le consentement contact.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $student_id
 * @property string $admission_number
 * @property string $applicant_name
 * @property string|null $contact_reference
 * @property int|null $academic_year_id
 * @property string $status
 * @property bool $consent_marketing
 * @property Carbon|null $consent_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EduStudent|null $student
 * @property-read EduAcademicYear|null $academicYear
 *
 * @mixin Builder<static>
 */
class EduAdmission extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ADMITTED = 'admitted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ADMITTED,
        self::STATUS_REJECTED,
        self::STATUS_ENROLLED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'edu_admissions';

    protected $fillable = [
        'company_id',
        'student_id',
        'admission_number',
        'applicant_name',
        'contact_reference',
        'academic_year_id',
        'status',
        'consent_marketing',
        'consent_at',
        'submitted_at',
        'decided_at',
        'decided_by',
        'metadata',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'contact_reference' => 'encrypted',
        'academic_year_id' => 'integer',
        'status' => 'string',
        'consent_marketing' => 'boolean',
        'consent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'decided_by' => 'integer',
        // PII — chiffré au repos (RGPD / loi 18-07, pattern AccountingContact).
        'metadata' => 'encrypted:array',
    ];

    /**
     * Élève issu de la conversion (null tant que non converti).
     *
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    /**
     * Année scolaire visée par le dossier (EDU-003, #5819).
     *
     * @return BelongsTo<EduAcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(EduAcademicYear::class, 'academic_year_id');
    }
}
