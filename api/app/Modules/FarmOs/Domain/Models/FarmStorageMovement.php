<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmStorageMovementType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.2 Mouvement de stock d'une recolte (journal immuable).
 *
 * Chaque changement de quantite d'un lot laisse EXACTEMENT une ligne ici. La
 * quantite est toujours POSITIVE : le sens est porte par `movement_type`.
 * `quantity_after` conserve la quantite restante du lot apres le mouvement, ce
 * qui rend l'historique relisible sans recalcul.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_harvest_lot_id
 * @property string $movement_type
 * @property string $quantity
 * @property string $quantity_after
 * @property int|null $from_location_id
 * @property int|null $to_location_id
 * @property int|null $sale_document_id
 * @property int|null $sale_line_id
 * @property int|null $counterparty_id
 * @property string|null $unit_price
 * @property string|null $currency
 * @property string|null $reason
 * @property int|null $recorded_by
 * @property string|null $client_movement_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmStorageMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_storage_movements';

    protected $fillable = [
        'company_id',
        'farm_harvest_lot_id',
        'movement_type',
        'quantity',
        'quantity_after',
        'from_location_id',
        'to_location_id',
        'sale_document_id',
        'sale_line_id',
        'counterparty_id',
        'unit_price',
        'currency',
        'reason',
        'recorded_by',
        'client_movement_id',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): FarmStorageMovementType
    {
        return FarmStorageMovementType::tryFrom($this->movement_type) ?? FarmStorageMovementType::Adjustment;
    }

    /**
     * @return BelongsTo<FarmHarvestLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(FarmHarvestLot::class, 'farm_harvest_lot_id');
    }
}
