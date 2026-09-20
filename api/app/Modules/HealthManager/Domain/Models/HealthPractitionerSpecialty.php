<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affectation praticien → spécialité (n-n) — Issue #7786 (BC-31).
 *
 * Unique par tenant (UNIQUE company_id+practitioner_id+specialty_id) ;
 * FK composites anti cross-tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property int $practitioner_id
 * @property int $specialty_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthPractitionerSpecialty extends Model
{
    use BelongsToCompany;

    protected $table = 'health_practitioner_specialties';

    protected $fillable = [
        'company_id',
        'practitioner_id',
        'specialty_id',
    ];

    protected $casts = [
        'practitioner_id' => 'integer',
        'specialty_id' => 'integer',
    ];

    /**
     * @return BelongsTo<HealthPractitioner, $this>
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(HealthPractitioner::class, 'practitioner_id');
    }

    /**
     * @return BelongsTo<HealthSpecialty, $this>
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(HealthSpecialty::class, 'specialty_id');
    }
}
