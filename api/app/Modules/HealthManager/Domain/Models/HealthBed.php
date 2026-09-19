<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lit d'hospitalisation — Issue #7786 (BC-30).
 *
 * Rattaché à une salle (FK composite anti cross-tenant). Code unique par
 * tenant ; statut borné (free|occupied|maintenance, CHECK en base) —
 * invariant admissions §4 : lit `free` requis, occupation en transaction.
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

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_FREE,
        self::STATUS_OCCUPIED,
        self::STATUS_MAINTENANCE,
    ];

    protected $table = 'health_beds';

    protected $fillable = [
        'company_id',
        'room_id',
        'code',
        'status',
    ];

    protected $casts = [
        'room_id' => 'integer',
        'status' => 'string',
    ];

    /**
     * @return BelongsTo<HealthRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(HealthRoom::class, 'room_id');
    }
}
