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
 * Ordonnance — HC-005 (#7789, BC-30).
 *
 * Émise lors d'une consultation, porte TOUJOURS ≥ 1 ligne de médicament
 * (health_prescription_items, validé à la création). `patient_id` en
 * propre (FK composite) pour l'historique PAR PATIENT. Donnée de santé :
 * RBAC strict, la réception n'y accède jamais ; `notes` chiffrées au repos.
 *
 * @property int $id
 * @property string $company_id
 * @property int $consultation_id
 * @property int $patient_id
 * @property int $practitioner_id
 * @property Carbon $prescribed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthPrescription extends Model
{
    use BelongsToCompany;

    protected $table = 'health_prescriptions';

    /**
     * Liens serveur uniquement : `consultation_id`, `patient_id` et
     * `practitioner_id` sont dérivés de la consultation côté contrôleur
     * (jamais mass-assignés) ; `company_id` posé par BelongsToCompany.
     */
    protected $fillable = [
        'prescribed_at',
        'notes',
    ];

    protected $casts = [
        'prescribed_at' => 'datetime',
        // Notes d'ordonnance chiffrées au repos (art. 9 RGPD).
        'notes' => 'encrypted',
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
     * @return HasMany<HealthPrescriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(HealthPrescriptionItem::class, 'prescription_id');
    }
}
