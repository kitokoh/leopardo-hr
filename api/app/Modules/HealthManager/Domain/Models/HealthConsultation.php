<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Consultation médicale — HC-005 (#7789, BC-30).
 *
 * DOSSIER MÉDICAL (art. 9 RGPD, sensibilité maximale) : le praticien
 * documente motif, examen clinique, diagnostic, constantes vitales et
 * notes. `clinical_exam`, `diagnosis` et `notes` sont chiffrés AU REPOS
 * (casts `encrypted`, pattern HealthPatient).
 *
 * RBAC strict (HealthConsultationPolicy) : praticiens actifs et direction
 * lisent ; seul le praticien AUTEUR (ou la direction) modifie ; la
 * réception n'accède JAMAIS au contenu médical (critère HC-005).
 *
 * @property int $id
 * @property string $company_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int|null $appointment_id
 * @property Carbon $consulted_at
 * @property string $reason
 * @property string|null $clinical_exam
 * @property string|null $diagnosis
 * @property string|null $weight_kg
 * @property string|null $height_cm
 * @property string|null $blood_pressure
 * @property string|null $temperature_c
 * @property int|null $pulse_bpm
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthConsultation extends Model
{
    use BelongsToCompany;

    protected $table = 'health_consultations';

    /**
     * `practitioner_id` HORS $fillable : l'auteur est posé côté serveur
     * (praticien acteur, ou choix explicite de la direction) ;
     * `company_id` posé par BelongsToCompany (pattern strict #7712).
     */
    protected $fillable = [
        'patient_id',
        'appointment_id',
        'consulted_at',
        'reason',
        'clinical_exam',
        'diagnosis',
        'weight_kg',
        'height_cm',
        'blood_pressure',
        'temperature_c',
        'pulse_bpm',
        'notes',
    ];

    protected $casts = [
        'consulted_at' => 'datetime',
        // Contenu médical chiffré au repos (art. 9 RGPD).
        'clinical_exam' => 'encrypted',
        'diagnosis' => 'encrypted',
        'notes' => 'encrypted',
    ];

    /**
     * @return BelongsTo<HealthPatient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(HealthPatient::class, 'patient_id');
    }

    /**
     * @return BelongsTo<HealthPractitioner, $this>
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(HealthPractitioner::class, 'practitioner_id');
    }

    /**
     * @return HasMany<HealthPrescription, $this>
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(HealthPrescription::class, 'consultation_id');
    }
}
