<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Livraison de carburant reçue par une station-service — FUEL-009 (#5803).
 *
 * Cycle : draft → received (mouvement de stock `in/delivery` créé) →
 * verified (verrouillage manager). `idempotency_key` unique par tenant :
 * un rejeu réseau retourne la livraison existante, zéro doublon.
 * `quantity_minor` en unités mineures entières (jamais de flottants métier).
 *
 * @property int $id
 * @property string $company_id
 * @property int $station_id
 * @property int|null $tank_id
 * @property string $product_type
 * @property int $quantity_minor
 * @property string|null $supplier
 * @property string|null $reference_number
 * @property string $status draft|received|verified
 * @property Carbon $delivered_at
 * @property string $idempotency_key
 * @property int|null $received_by
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property int|null $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelDelivery extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_deliveries';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_VERIFIED = 'verified';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_RECEIVED,
        self::STATUS_VERIFIED,
    ];

    protected $fillable = [
        'company_id',
        'station_id',
        'tank_id',
        'product_type',
        'quantity_minor',
        'supplier',
        'reference_number',
        'status',
        'delivered_at',
        'idempotency_key',
        'received_by',
        'verified_by',
        'verified_at',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'tank_id' => 'integer',
            'quantity_minor' => 'integer',
            'delivered_at' => 'datetime',
            'received_by' => 'integer',
            'verified_by' => 'integer',
            'verified_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<FuelStation, $this> */
    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'station_id');
    }
}
