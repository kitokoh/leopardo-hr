<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T2.1 Lot d'intrant (traçabilité + péremption).
 *
 * `expiry_date` est NULLABLE : tous les intrants ne périment pas. Farm OS
 * n'invente jamais une date de péremption.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $farm_input_id
 * @property string $lot_number
 * @property Carbon|null $expiry_date
 * @property string $quantity
 * @property int|null $supplier_id
 * @property Carbon|null $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmInputLot extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_input_lots';

    protected $fillable = [
        'company_id',
        'farm_input_id',
        'lot_number',
        'expiry_date',
        'quantity',
        'supplier_id',
        'received_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:3',
        'received_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmInput, $this>
     */
    public function input(): BelongsTo
    {
        return $this->belongsTo(FarmInput::class, 'farm_input_id');
    }

    /**
     * @return HasMany<FarmInputStockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(FarmInputStockMovement::class, 'farm_input_lot_id');
    }

    /**
     * Le lot est-il périmé à la date donnée ? Un lot SANS date de péremption
     * n'est jamais considéré comme périmé (on ne suppose rien).
     */
    public function isExpiredAt(?Carbon $at = null): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->lt(($at ?? Carbon::now())->startOfDay());
    }
}
