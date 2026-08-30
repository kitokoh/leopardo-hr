<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Modules\Delivery\Domain\Enums\DeliveryEventType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Événement de tracking (BC-26 DELIVERY) — idempotent (unique
 * (company_id, delivery_id, type, event_at)), géolocalisation optionnelle.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $delivery_id
 * @property string $type
 * @property Carbon $event_at
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $origin
 * @property string|null $idempotency_key
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Delivery|null $delivery
 *
 * @mixin Builder<static>
 */
class DeliveryEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_events';

    protected $fillable = [
        'company_id',
        'delivery_id',
        'type',
        'event_at',
        'latitude',
        'longitude',
        'origin',
        'idempotency_key',
        'payload',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'payload' => 'array',
    ];

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function typeEnum(): DeliveryEventType
    {
        return DeliveryEventType::from($this->type);
    }
}
