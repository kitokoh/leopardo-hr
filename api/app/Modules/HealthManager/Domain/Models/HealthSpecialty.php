<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Spécialité médicale — Issue #7786 (BC-30).
 *
 * Référentiel par tenant (code unique par tenant), seed standard à
 * l'activation de la solution. Liée n-n aux praticiens via
 * `health_practitioner_specialties`.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthSpecialty extends Model
{
    use BelongsToCompany;

    protected $table = 'health_specialties';

    protected $fillable = [
        'company_id',
        'name',
        'code',
    ];

    /**
     * @return HasMany<HealthPractitionerSpecialty, $this>
     */
    public function practitionerSpecialties(): HasMany
    {
        return $this->hasMany(HealthPractitionerSpecialty::class, 'specialty_id');
    }
}
