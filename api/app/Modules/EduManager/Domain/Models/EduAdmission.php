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
 * @property Carbon $applied_at
 * @property Carbon|null $converted_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $applicant_name
 * @property string|null $contact_reference
 * @property bool $consent_marketing
 * @property Carbon|null $consent_at
 * @property Carbon|null $consent_revoked_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property array<string, mixed>|null $metadata
 *
 * @mixin Builder<static>
 */
class EduAdmission extends Model
{
    use BelongsToCompany;

    public const STATUS_NEW = 'new';

    /**
     * Statut d'entrée « en attente » — vocabulaire v2 (#5820). Il existe DÉJÀ
     * dans la contrainte CHECK `edu_admissions_status_check` (vocabulaire
     * unifié par la migration de réparation) : seule la constante manquait.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_DOCUMENT_PENDING = 'document_pending';

    public const STATUS_REVIEW = 'review';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_WAITLISTED = 'waitlisted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CONVERTED = 'converted';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PENDING,
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
        self::STATUS_CANCELLED,
    ];

    public const STATUS_ENROLLED = 'enrolled';

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
        // Génération v2 (#5820) : nom consolidé + référence de contact
        // chiffrée au repos. Colonnes présentes en base (migration de
        // réparation) mais absentes du modèle → `AdmissionService` (v2)
        // lisait `null`, la conversion d'un dossier créait un élève sans nom
        // (violation NOT NULL sur `edu_students.display_name`).
        'applicant_name',
        'contact_reference',
        'consent_marketing',
        'consent_at',
        'consent_revoked_at',
        'submitted_at',
        'decided_at',
        'decided_by',
        'metadata',
        'status',
        'source',
        'external_id',
        'crm_contact_id',
        'consent_contact',
        'consented_at',
        'applied_at',
        'converted_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'academic_year_id' => 'integer',
        'campus_id' => 'integer',
        'applicant_birth_date' => 'date',
        'consent_contact' => 'boolean',
        'consented_at' => 'datetime',
        // v2 : PII chiffrée au repos (enveloppe Laravel) + consentement
        // marketing horodaté.
        'contact_reference' => 'encrypted',
        'consent_marketing' => 'boolean',
        'consent_at' => 'datetime',
        'consent_revoked_at' => 'datetime',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'decided_by' => 'integer',
        'metadata' => 'array',
        'applied_at' => 'date',
        'converted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<EduStudent, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(EduStudent::class, 'student_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(EduAcademicYear::class, 'academic_year_id');
    }
}
