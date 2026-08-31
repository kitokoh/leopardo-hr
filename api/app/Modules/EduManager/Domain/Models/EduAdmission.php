<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dossier d'admission scolaire — Issue #5820 (EDU-004).
 *
 * Tenant-scoped. Pipeline : new → document_pending → review → accepted |
 * waitlisted | rejected | cancelled, puis converted à la création de l'élève.
 * `external_id` (unique par tenant) rend la création idempotente ; les
 * doublons sont détectés sur (company_id, external_id).
 *
 * Lien CRM client : `crm_contact_id` est une simple référence de contrat
 * (SANS FK) — le CRM commercial plateforme reste inaccessible (spec §2).
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
 * @property int $academic_year_id
 * @property int|null $campus_id
 * @property string $admission_number
 * @property string $applicant_first_name
 * @property string $applicant_last_name
 * @property string|null $applicant_email
 * @property string|null $applicant_phone
 * @property Carbon|null $applicant_birth_date
 * @property string $status
 * @property string|null $source
 * @property string|null $external_id
 * @property string|null $crm_contact_id
 * @property bool $consent_contact
 * @property Carbon|null $consented_at
 * @property Carbon|null $consent_revoked_at
 * @property Carbon $applied_at
 * @property Carbon|null $converted_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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

    public const STATUS_NEW = 'new';

    public const STATUS_DOCUMENT_PENDING = 'document_pending';

    public const STATUS_REVIEW = 'review';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_WAITLISTED = 'waitlisted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CONVERTED = 'converted';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_DOCUMENT_PENDING,
        self::STATUS_REVIEW,
        self::STATUS_ACCEPTED,
        self::STATUS_WAITLISTED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_CONVERTED,
    ];

    /** Statuts terminaux (plus aucune transition autorisée). */
    public const TERMINAL_STATUSES = [
        self::STATUS_CONVERTED,
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
        'academic_year_id',
        'campus_id',
        'admission_number',
        'applicant_first_name',
        'applicant_last_name',
        'applicant_email',
        'applicant_phone',
        'applicant_birth_date',
        'status',
        'source',
        'external_id',
        'crm_contact_id',
        'consent_contact',
        'consented_at',
        'consent_revoked_at',
        'applied_at',
        'converted_at',
        'notes',
        'created_by',
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
        'academic_year_id' => 'integer',
        'campus_id' => 'integer',
        'applicant_birth_date' => 'date',
        'consent_contact' => 'boolean',
        'consented_at' => 'datetime',
        'consent_revoked_at' => 'datetime',
        'applied_at' => 'date',
        'converted_at' => 'datetime',
    ];

    /**
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

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
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
