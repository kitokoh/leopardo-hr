<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmStockMovementType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.1 Mouvement de stock d'intrant (JOURNAL IMMUABLE).
 *
 * Chaque changement de quantité d'un lot laisse exactement une ligne ici. Une
 * ligne de mouvement n'est JAMAIS mise à jour : on en ajoute une nouvelle
 * (traçabilité, exigence de la mission). `quantity_after` rend l'historique
 * relisible sans rejouer tous les mouvements.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_input_id
 * @property int|null $farm_input_lot_id
 * @property string $movement_type
 * @property string $quantity
 * @property string $quantity_after
 * @property int|null $farm_operation_id
 * @property int|null $parcel_id
 * @property string|null $reason
 * @property int|null $recorded_by
 * @property string|null $client_movement_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmInputStockMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_input_stock_movements';

    protected $fillable = [
        'company_id',
        'farm_input_id',
        'farm_input_lot_id',
        'movement_type',
        'quantity',
        'quantity_after',
        'farm_operation_id',
        'parcel_id',
        'reason',
        'recorded_by',
        'client_movement_id',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function typeEnum(): ?FarmStockMovementType
    {
        return FarmStockMovementType::tryFrom($this->movement_type);
    }

    /**
     * @return BelongsTo<FarmInput, $this>
     */
    public function input(): BelongsTo
    {
        return $this->belongsTo(FarmInput::class, 'farm_input_id');
    }

    /**
     * @return BelongsTo<FarmInputLot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(FarmInputLot::class, 'farm_input_lot_id');
    }
}
