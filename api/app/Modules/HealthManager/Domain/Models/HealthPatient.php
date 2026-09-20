<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Dossier administratif patient — Issue #7787 (BC-31).
 *
 * MRN `PAT-YYYY-NNNN` séquentiel par tenant/année, généré serveur.
 * PII et données médicales chiffrées AU REPOS (casts `encrypted`, pattern
 * AccountingContact/EduStudent) ; `full_name` nominative en clair (listes,
 * RBAC, jamais hors tenant). Jamais supprimé physiquement : archivage via
 * `status` (active|deceased|archived).
 *
 * @property int $id
 * @property string $company_id
 * @property string $mrn
 * @property string $full_name
 * @property string|null $birth_date_encrypted
 * @property string $sex
 * @property string|null $blood_group
 * @property string|null $phone_encrypted
 * @property string|null $email_encrypted
 * @property string|null $address_encrypted
 * @property string|null $emergency_contact_name_encrypted
 * @property string|null $emergency_contact_phone_encrypted
 * @property string|null $insurance_provider_encrypted
 * @property string|null $insurance_number_encrypted
 * @property string|null $allergies_encrypted
 * @property string|null $medical_history_encrypted
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthPatient extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECEASED = 'deceased';

    public const STATUS_ARCHIVED = 'archived';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DECEASED,
        self::STATUS_ARCHIVED,
    ];

    public const SEX_MALE = 'male';

    public const SEX_FEMALE = 'female';

    public const SEX_OTHER = 'other';

    /** @var list<string> */
    public const SEXES = [
        self::SEX_MALE,
        self::SEX_FEMALE,
        self::SEX_OTHER,
    ];

    protected $table = 'health_patients';

    protected $fillable = [
        'company_id',
        'mrn',
        'full_name',
        'birth_date_encrypted',
        'sex',
        'blood_group',
        'phone_encrypted',
        'email_encrypted',
        'address_encrypted',
        'emergency_contact_name_encrypted',
        'emergency_contact_phone_encrypted',
        'insurance_provider_encrypted',
        'insurance_number_encrypted',
        'allergies_encrypted',
        'medical_history_encrypted',
        'status',
    ];

    protected $casts = [
        'sex' => 'string',
        'status' => 'string',
        // PII / données médicales — chiffrées au repos (RGPD / loi 18-07).
        'birth_date_encrypted' => 'encrypted',
        'phone_encrypted' => 'encrypted',
        'email_encrypted' => 'encrypted',
        'address_encrypted' => 'encrypted',
        'emergency_contact_name_encrypted' => 'encrypted',
        'emergency_contact_phone_encrypted' => 'encrypted',
        'insurance_provider_encrypted' => 'encrypted',
        'insurance_number_encrypted' => 'encrypted',
        'allergies_encrypted' => 'encrypted',
        'medical_history_encrypted' => 'encrypted',
    ];

    /**
     * @return HasMany<HealthAppointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(HealthAppointment::class, 'patient_id');
    }

    /**
     * @return HasMany<HealthConsultation, $this>
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(HealthConsultation::class, 'patient_id');
    }

    /**
     * @return HasMany<HealthAdmission, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(HealthAdmission::class, 'patient_id');
    }

    /**
     * @return HasMany<HealthInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(HealthInvoice::class, 'patient_id');
    }
}
