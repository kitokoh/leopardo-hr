<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Patient d'un établissement de santé — HC-003 (#7787, BC-30).
 *
 * Registre ADMINISTRATIF (pas de dossier médical de soins) : identité,
 * naissance, contacts, personne à prévenir, assurance, allergies et
 * antécédents en texte libre. Sensibilité MAXIMALE (art. 9 RGPD) :
 *   - `mrn` généré CÔTÉ SERVEUR (`PAT-YYYY-NNNN`), unique par tenant,
 *     jamais accepté depuis la requête ;
 *   - `birth_date`, `insurance_number`, `allergies`, `medical_history`
 *     chiffrés AU REPOS (casts `encrypted`, pattern EduStudent) ;
 *   - ARCHIVAGE au lieu de suppression : statut `archived` + SoftDeletes,
 *     jamais de suppression physique via l'API.
 *
 * @property int $id
 * @property string $company_id
 * @property string $mrn
 * @property string $first_name
 * @property string $last_name
 * @property string $sex
 * @property string|null $birth_date
 * @property string|null $blood_group
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property string|null $insurance_provider
 * @property string|null $insurance_number
 * @property string|null $allergies
 * @property string|null $medical_history
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @mixin Builder<static>
 */
class HealthPatient extends Model
{
    use BelongsToCompany;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DECEASED = 'deceased';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DECEASED,
        self::STATUS_ARCHIVED,
    ];

    public const SEXES = ['female', 'male', 'other', 'unknown'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    protected $table = 'health_patients';

    /**
     * `mrn` et `company_id` ne sont JAMAIS mass-assignables : le MRN est
     * généré côté serveur, le tenant est posé par BelongsToCompany.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'sex',
        'birth_date',
        'blood_group',
        'phone',
        'email',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'insurance_provider',
        'insurance_number',
        'allergies',
        'medical_history',
        'status',
    ];

    protected $casts = [
        // Données sensibles chiffrées au repos (art. 9 RGPD).
        'birth_date' => 'encrypted',
        'insurance_number' => 'encrypted',
        'allergies' => 'encrypted',
        'medical_history' => 'encrypted',
    ];
}
