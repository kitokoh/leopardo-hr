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
 * Ordonnance (prescription) — Issue #7789 (BC-30).
 *
 * Liée à une consultation ; notes chiffrées AU REPOS. Contenu médical :
 * visible praticiens + direction UNIQUEMENT (RBAC §2).
 *
 * @property int $id
 * @property string $company_id
 * @property int $consultation_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property Carbon $prescribed_at
 * @property string|null $notes_encrypted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthPrescription extends Model
{
    use BelongsToCompany;

    protected $table = 'health_prescriptions';

    protected $fillable = [
        'company_id',
        'consultation_id',
        'patient_id',
        'practitioner_id',
        'prescribed_at',
        'notes_encrypted',
    ];

    protected $casts = [
        'consultation_id' => 'integer',
        'patient_id' => 'integer',
        'practitioner_id' => 'integer',
        'prescribed_at' => 'datetime',
        // Contenu médical — chiffré au repos (RGPD / loi 18-07).
        'notes_encrypted' => 'encrypted',
    ];

    /**
     * @return BelongsTo<HealthConsultation, $this>
     */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(HealthConsultation::class, 'consultation_id');
    }

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
     * @return HasMany<HealthPrescriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HealthPrescriptionItem::class, 'prescription_id');
    }
}
