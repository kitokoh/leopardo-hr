<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Modules\Delivery\Domain\Enums\CodSettlementStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Règlement COD (contre-remboursement) (BC-26 DELIVERY) — montants en minor
 * units, référence écriture BC-08, posting idempotent.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $route_id
 * @property int|null $driver_id
 * @property int $expected_minor
 * @property int $collected_minor
 * @property int $commission_minor
 * @property string $status
 * @property string|null $accounting_ref
 * @property Carbon|null $settled_at
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DeliveryRoute|null $route
 *
 * @mixin Builder<static>
 */
class DeliveryCodSettlement extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_cod_settlements';

    protected $fillable = [
        'company_id',
        'route_id',
        'driver_id',
        'expected_minor',
        'collected_minor',
        'commission_minor',
        'collected_at',
        'status',
        'accounting_ref',
        'settled_at',
        'idempotency_key',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'settled_at' => 'datetime',
        'settled_at' => 'datetime',
        'expected_minor' => 'int',
        'collected_minor' => 'int',
        'commission_minor' => 'int',
    ];

    /** @return BelongsTo<DeliveryRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    public function statusEnum(): CodSettlementStatus
    {
        return CodSettlementStatus::from($this->status);
    }
}
