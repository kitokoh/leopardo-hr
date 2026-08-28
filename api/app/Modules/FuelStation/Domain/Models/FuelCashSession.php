<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Session de caisse d'une station-service (FUEL-007, issue #5801).
 *
 * Cycle : open → (mouvements in/out) → close (écart calculé serveur) →
 * approved (verrouillage manager). Statuts terminaux : closed, approved.
 * `station_id` (uuid, nullable) résolu par FUEL-002.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $station_id
 * @property int $opened_by
 * @property Carbon $opened_at
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property float $opening_balance
 * @property float|null $closing_balance
 * @property float|null $expected_balance
 * @property float|null $variance
 * @property string $status open|closed|approved
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @mixin Builder<static>
 */
class FuelCashSession extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'fuel_cash_sessions';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_APPROVED = 'approved';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_CLOSED, self::STATUS_APPROVED];

    protected $fillable = [
        'company_id',
        'station_id',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'variance',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'approved_at' => 'datetime',
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'expected_balance' => 'float',
        'variance' => 'float',
    ];

    /** @return HasMany<FuelCashSessionMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(FuelCashSessionMovement::class, 'session_id')->orderBy('created_at');
    }
}
