<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Transfert de stock inter-emplacements (BC-17 RETAIL, #9268).
 *
 * Objet métier qui relie les DEUX mouvements d'un transfert : la sortie
 * (`transfer_out`, emplacement source) et l'entrée (`transfer_in`,
 * emplacement destination) portent tous deux `reference_type =
 * 'retail_transfer'` et `reference_id = <id de ce transfert>`.
 *
 * Créé UNIQUEMENT par `RetailStockService::transfer()`, dans la même
 * transaction que les deux mouvements : un transfert sans ses deux mouvements
 * ne peut pas exister (atomicité — pas de sortie sans entrée).
 *
 * @property int $id
 * @property string $company_id
 * @property int $from_location_id
 * @property int $to_location_id
 * @property int $product_id
 * @property numeric-string $quantity
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailStockTransfer extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_stock_transfers';

    protected $fillable = [
        'company_id',
        'from_location_id',
        'to_location_id',
        'product_id',
        'quantity',
        'note',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<RetailLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(RetailLocation::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<RetailLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(RetailLocation::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<RetailProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'product_id');
    }
}
