<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'ordonnance — HC-005 (#7789, BC-30) : médicament, dosage,
 * fréquence, durée, instructions. Donnée de santé (RBAC strict).
 * `company_id` NOT NULL posé par BelongsToCompany (pattern strict #7712) ;
 * FK composite vers l'ordonnance (cross-tenant impossible).
 *
 * @property int $id
 * @property string $company_id
 * @property int $prescription_id
 * @property string $medication
 * @property string $dosage
 * @property string $frequency
 * @property string $duration
 * @property string|null $instructions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthPrescriptionItem extends Model
{
    use BelongsToCompany;

    protected $table = 'health_prescription_items';

    protected $fillable = [
        'medication',
        'dosage',
        'frequency',
        'duration',
        'instructions',
    ];

    /**
     * @return BelongsTo<HealthPrescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(HealthPrescription::class, 'prescription_id');
    }
}
