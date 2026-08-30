<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Modules\Delivery\Domain\Enums\StopStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Arrêt d'une tournée (BC-26 DELIVERY) — ordre de passage, ETA/ETD, POD
 * (proof_id → BC-20 documents par valeur).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $route_id
 * @property int $delivery_id
 * @property int $sort_order
 * @property string $status
 * @property string $address
 * @property string|null $contact
 * @property string|null $phone
 * @property Carbon|null $eta
 * @property Carbon|null $etd
 * @property int|null $proof_id
 * @property Carbon|null $arrived_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DeliveryRoute|null $route
 * @property-read Delivery|null $delivery
 *
 * @mixin Builder<static>
 */
class DeliveryStop extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_stops';

    protected $fillable = [
        'company_id',
        'route_id',
        'delivery_id',
        'sort_order',
        'status',
        'address',
        'contact',
        'phone',
        'eta',
        'etd',
        'proof_id',
        'arrived_at',
        'delivered_at',
    ];

    protected $casts = [
        'eta' => 'datetime',
        'etd' => 'datetime',
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
        'sort_order' => 'int',
    ];

    /** @return BelongsTo<DeliveryRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(DeliveryRoute::class, 'route_id');
    }

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function statusEnum(): StopStatus
    {
        return StopStatus::from($this->status);
    }
}
