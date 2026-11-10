<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Mouvement d'inventaire du module Retail (BC-17 RETAIL, #7673).
 *
 * `quantity_delta` est signe (negatif = sortie) ; `reason_code` est un code
 * controle (RetailStockReasonCode). `reference_type`/`reference_id` tracent
 * la source (ticket, bon de reception, transfert...). Journal append-only :
 * cree UNIQUEMENT via RetailStockService (pattern #6170).
 *
 * @property int $id
 * @property string $company_id
 * @property int $location_id
 * @property int $product_id
 * @property int|null $stock_level_id
 * @property numeric-string $quantity_delta
 * @property int|null $unit_cost_minor Cout d'acquisition unitaire (minor units) - base du cout moyen pondere (#9269)
 * @property RetailStockReasonCode $reason_code
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $note
 * @property int|null $user_id
 * @property string|null $idempotency_key Cle de deduplication du rejeu offline (#9270)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailInventoryMovement extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_inventory_movements';

    protected $fillable = [
        'company_id',
        'location_id',
        'product_id',
        'stock_level_id',
        'quantity_delta',
        'unit_cost_minor',
        'reason_code',
        'reference_type',
        'reference_id',
        'note',
        'user_id',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'unit_cost_minor' => 'integer',
            'reason_code' => RetailStockReasonCode::class,
        ];
    }

    /**
     * #9293 — journal APPEND-ONLY, garde fail-closed.
     *
     * Le docblock de classe revendiquait « journal append-only » depuis
     * #7673, mais AUCUNE garde ne l'appliquait : un `update()` ou un
     * `delete()` sur un mouvement passait silencieusement. Toute
     * l'architecture du stock Retail repose sur ce journal (tracabilite,
     * COGS, reconciliation, preuve d'audit) : un mouvement reecrit apres
     * coup desynchronise le niveau du journal, et un mouvement efface fait
     * disparaitre la consommation.
     *
     * Meme contrat que `PharmacyStockMovement` (#7800) : un mouvement ne se
     * corrige jamais, il se CONTRE-PASSE (nouveau mouvement inverse).
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('retail_inventory_movements est un journal immuable (append-only).');
        });

        static::deleting(function (): void {
            throw new LogicException('retail_inventory_movements est un journal immuable (append-only).');
        });
    }

    /**
     * @return BelongsTo<RetailLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(RetailLocation::class, 'location_id');
    }

    /**
     * @return BelongsTo<RetailProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(RetailProduct::class, 'product_id');
    }

    /**
     * @return BelongsTo<RetailStockLevel, $this>
     */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(RetailStockLevel::class, 'stock_level_id');
    }
}
