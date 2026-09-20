<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ordonnance — PHARMA-006 (#7803). Référence unique par tenant ; le patient
 * est une PII santé (permissions strictes, JAMAIS exposée cross-tenant).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $prescriber_id
 * @property string $patient_name
 * @property string|null $patient_contact
 * @property Carbon $prescribed_at
 * @property string $reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PharmacyPrescriber|null $prescriber
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyPrescription extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_prescriptions';

    protected $fillable = [
        'company_id',
        'prescriber_id',
        'patient_name',
        'patient_contact',
        'prescribed_at',
        'reference',
        'notes',
    ];

    protected $casts = [
        'prescriber_id' => 'integer',
        'prescribed_at' => 'date',
    ];

    /** @return BelongsTo<PharmacyPrescriber, $this> */
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(PharmacyPrescriber::class, 'prescriber_id');
    }
}
