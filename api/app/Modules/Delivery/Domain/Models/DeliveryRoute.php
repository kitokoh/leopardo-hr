<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Modules\Delivery\Domain\Enums\RouteStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tournée de livraison (BC-26 DELIVERY) — 1 livreur + 1 véhicule par date,
 * clôture idempotente (totaux dénormalisés).
 *
 * @property int $id
 * @property string|null $company_id
 * @property Carbon $route_date
 * @property int|null $driver_id
 * @property string|null $vehicle_code
 * @property string|null $zone
 * @property string $status
 * @property int $deliveries_count
 * @property int $delivered_count
 * @property int $failed_count
 * @property int $cod_collected_minor
 * @property Carbon|null $closed_at
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryStop> $stops
 *
 * @mixin Builder<static>
 */
class DeliveryRoute extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_routes';

    protected $fillable = [
        'company_id',
        'route_date',
        'driver_id',
        'vehicle_code',
        'zone',
        'status',
        'deliveries_count',
        'delivered_count',
        'failed_count',
        'cod_collected_minor',
        'closed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'route_date' => 'date',
        'closed_at' => 'datetime',
        'deliveries_count' => 'int',
        'delivered_count' => 'int',
        'failed_count' => 'int',
        'cod_collected_minor' => 'int',
    ];

    /** @return HasMany<DeliveryStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class, 'route_id')->orderBy('sort_order');
    }

    public function statusEnum(): RouteStatus
    {
        return RouteStatus::from($this->status);
    }
}
