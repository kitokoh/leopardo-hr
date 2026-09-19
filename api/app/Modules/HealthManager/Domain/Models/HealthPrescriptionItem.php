<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne d'ordonnance (médicament + posologie) — Issue #7789 (BC-30).
 *
 * @property int $id
 * @property string $company_id
 * @property int $prescription_id
 * @property string $medication
 * @property string|null $dosage
 * @property string|null $frequency
 * @property string|null $duration
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
        'company_id',
        'prescription_id',
        'medication',
        'dosage',
        'frequency',
        'duration',
        'instructions',
    ];

    protected $casts = [
        'prescription_id' => 'integer',
    ];

    /**
     * @return BelongsTo<HealthPrescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(HealthPrescription::class, 'prescription_id');
    }
}
