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
 * Consultation médicale — Issue #7789 (BC-31).
 *
 * Contenu MÉDICAL chiffré AU REPOS (casts `encrypted` / `encrypted:array`) :
 * examen clinique, diagnostic, constantes vitales (weight_kg, height_cm,
 * blood_pressure, temperature_c, pulse_bpm) et notes — visibles praticiens
 * + direction UNIQUEMENT (RBAC §2). Jamais supprimée physiquement (spec §3).
 *
 * @property int $id
 * @property string $company_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property int|null $appointment_id
 * @property Carbon $consulted_at
 * @property string|null $reason
 * @property string|null $clinical_exam_encrypted
 * @property string|null $diagnosis_encrypted
 * @property array<string, mixed>|null $vitals_encrypted
 * @property string|null $notes_encrypted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthConsultation extends Model
{
    use BelongsToCompany;

    protected $table = 'health_consultations';

    protected $fillable = [
        'company_id',
        'patient_id',
        'practitioner_id',
        'appointment_id',
        'consulted_at',
        'reason',
        'clinical_exam_encrypted',
        'diagnosis_encrypted',
        'vitals_encrypted',
        'notes_encrypted',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'practitioner_id' => 'integer',
        'appointment_id' => 'integer',
        'consulted_at' => 'datetime',
        // Contenu médical — chiffré au repos (RGPD / loi 18-07).
        'clinical_exam_encrypted' => 'encrypted',
        'diagnosis_encrypted' => 'encrypted',
        'vitals_encrypted' => 'encrypted:array',
        'notes_encrypted' => 'encrypted',
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
     * @return BelongsTo<HealthAppointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(HealthAppointment::class, 'appointment_id');
    }

    /**
     * @return HasMany<HealthPrescription, $this>
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(HealthPrescription::class, 'consultation_id');
    }
}
