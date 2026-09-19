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
 * Salle d'un établissement de santé — HC-002 (#7786, BC-30).
 *
 * Une salle appartient à UN service médical (FK composite en base) ; les
 * lits (health_beds) lui sont rattachés par FK composite.
 *
 * @property int $id
 * @property string $company_id
 * @property int $department_id
 * @property string $code
 * @property string $name
 * @property string $room_type
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthRoom extends Model
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

    public const TYPES = [
        'consultation',
        'hospitalization',
        'surgery',
        'emergency',
        'other',
    ];

    protected $table = 'health_rooms';

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'room_type',
        'status',
    ];

    /**
     * @return BelongsTo<HealthDepartment, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(HealthDepartment::class, 'department_id');
    }

    /**
     * @return HasMany<HealthBed, $this>
     */
    public function beds(): HasMany
    {
        return $this->hasMany(HealthBed::class, 'room_id');
    }
}
