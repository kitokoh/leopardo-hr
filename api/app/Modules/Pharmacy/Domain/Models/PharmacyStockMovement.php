<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Mouvement de stock d'officine — PHARMA-003 (#7800).
 *
 * Journal IMMUABLE append-only : chaque changement de quantité d'un lot
 * trace exactement un mouvement signé (`quantity_delta`). Aucune mise à
 * jour ni suppression applicative (gardes `updating`/`deleting` fail-closed)
 * — l'ordonnancier des produits contrôlés (PHARMA-006) en dérive.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $product_id
 * @property int|null $batch_id
 * @property string $type
 * @property int $quantity_delta
 * @property string|null $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $created_by_employee_id
 * @property Carbon|null $created_at
 * @property-read PharmacyProduct|null $product
 * @property-read PharmacyBatch|null $batch
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyStockMovement extends Model
{
    use BelongsToCompany;

    public const TYPES = ['receipt', 'sale', 'adjustment', 'expiry_writeoff', 'return'];

    public const UPDATED_AT = null;

    protected $table = 'pharmacy_stock_movements';

    protected $fillable = [
        'company_id',
        'product_id',
        'batch_id',
        'type',
        'quantity_delta',
        'reason',
        'reference_type',
        'reference_id',
        'created_by_employee_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'batch_id' => 'integer',
        'quantity_delta' => 'integer',
        'reference_id' => 'integer',
        'created_by_employee_id' => 'integer',
    ];

    protected static function booted(): void
    {
        // Append-only : un mouvement ne se corrige jamais, il se contre-passe
        // (nouveau mouvement inverse). Jamais d'effacement (réglementaire).
        static::updating(function (): void {
            throw new LogicException('pharmacy_stock_movements est un journal immuable (append-only).');
        });

        static::deleting(function (): void {
            throw new LogicException('pharmacy_stock_movements est un journal immuable (append-only).');
        });
    }

    /** @return BelongsTo<PharmacyProduct, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PharmacyProduct::class, 'product_id');
    }

    /** @return BelongsTo<PharmacyBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBatch::class, 'batch_id');
    }
}
