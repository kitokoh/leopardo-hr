<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lit d'un établissement de santé — HC-002 (#7786, BC-30).
 *
 * Un lit appartient à UNE salle (FK composite en base). Statut d'occupation
 * borné : libre | occupé | maintenance (critère d'acceptation HC-002).
 *
 * @property int $id
 * @property string $company_id
 * @property int $room_id
 * @property string $code
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthBed extends Model
{
    use BelongsToCompany;

    public const STATUS_FREE = 'free';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUSES = [
        self::STATUS_FREE,
        self::STATUS_OCCUPIED,
        self::STATUS_MAINTENANCE,
    ];

    protected $table = 'health_beds';

    protected $fillable = [
        'room_id',
        'code',
        'status',
    ];

    /**
     * @return BelongsTo<HealthRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HealthRoom::class, 'room_id');
    }
}
