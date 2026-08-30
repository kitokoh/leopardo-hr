<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Modules\Delivery\Domain\Enums\DeliverySource;
use App\Modules\Delivery\Domain\Enums\DeliveryStatus;
use App\Modules\Delivery\Domain\Exceptions\InvalidDeliveryTransitionException;
use App\Modules\Delivery\Domain\Support\DeliveryStateMachine;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Livraison (agrégat racine du module Delivery, BC-26 DELIVERY).
 *
 * Référence unique par tenant (DLV-YYYY-NNNNNN), source + source_reference
 * (unique (company_id, source, source_reference) → zéro doublon par commande
 * source), montants en minor units, transitions de statut verrouillées par
 * DeliveryStateMachine (POD obligatoire pour `delivered`, états terminaux non
 * réouvrables).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $reference
 * @property string $source
 * @property string|null $source_reference
 * @property string $type
 * @property string $status
 * @property int|null $weight_grams
 * @property int|null $volume_cm3
 * @property int $declared_value_minor
 * @property int|null $cod_amount_minor
 * @property string|null $pickup_contact
 * @property string|null $pickup_address
 * @property string $dropoff_contact
 * @property string|null $dropoff_phone
 * @property string $dropoff_address
 * @property Carbon|null $window_from
 * @property Carbon|null $window_to
 * @property string|null $idempotency_key
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $returned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DeliveryStatus $status_enum
 * @property-read DeliverySource $source_enum
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DeliveryEvent> $events
 *
 * @mixin Builder<static>
 */
class Delivery extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_deliveries';

    protected $fillable = [
        'company_id',
        'reference',
        'source',
        'source_reference',
        'type',
        'status',
        'weight_grams',
        'volume_cm3',
        'declared_value_minor',
        'cod_amount_minor',
        'pickup_contact',
        'pickup_address',
        'dropoff_contact',
        'dropoff_phone',
        'dropoff_address',
        'window_from',
        'window_to',
        'idempotency_key',
        'delivered_at',
        'failed_at',
        'returned_at',
    ];

    protected $casts = [
        'window_from' => 'datetime',
        'window_to' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'returned_at' => 'datetime',
        'weight_grams' => 'int',
        'volume_cm3' => 'int',
        'declared_value_minor' => 'int',
        'cod_amount_minor' => 'int',
    ];

    /** @return HasMany<DeliveryEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class, 'delivery_id');
    }

    public function statusEnum(): DeliveryStatus
    {
        return DeliveryStatus::from($this->status);
    }

    public function sourceEnum(): DeliverySource
    {
        return DeliverySource::from($this->source);
    }

    /**
     * Applique une transition de statut validée par la machine à états.
     *
     * @throws InvalidDeliveryTransitionException si la transition est illégale
     *                                             ou si `delivered` sans POD
     */
    public function transitionTo(DeliveryStatus $to, bool $hasProof = false): void
    {
        (new DeliveryStateMachine())->assertCanTransitionTo($this->statusEnum(), $to, $hasProof);

        $this->status = $to->value;

        if ($to === DeliveryStatus::Delivered) {
            $this->delivered_at = now();
        } elseif ($to === DeliveryStatus::Failed) {
            $this->failed_at = now();
        } elseif ($to === DeliveryStatus::Returned) {
            $this->returned_at = now();
        }
    }
}
