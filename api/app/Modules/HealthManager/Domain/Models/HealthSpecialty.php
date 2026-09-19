<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Spécialité médicale du tenant — HC-002 (#7786, BC-30).
 *
 * Référentiel seedé à l'activation de la solution (idempotent) puis
 * éditable par la direction.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthSpecialty extends Model
{
    use BelongsToCompany;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected $table = 'health_specialties';

    protected $fillable = [
        'code',
        'name',
        'status',
    ];

    /**
     * @return BelongsToMany<HealthPractitioner, $this>
     */
    public function practitioners(): BelongsToMany
    {
        return $this->belongsToMany(
            HealthPractitioner::class,
            'health_practitioner_specialty',
            'specialty_id',
            'practitioner_id'
        )->withTimestamps();
    }
}
