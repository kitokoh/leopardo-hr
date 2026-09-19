<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Mouvement de stock d'officine — PHARMA-003 (#7800).
 *
 * Journal IMMUABLE append-only : chaque changement de quantité d'un lot
 * trace un mouvement (delta signé, type contrôlé, raison, référence
 * polymorphe, employé auteur). Toute tentative d'UPDATE ou de DELETE lève
 * une LogicException — la correction d'une erreur de saisie passe par un
 * mouvement d'ajustement inverse, jamais par la réécriture de l'historique
 * (exigence de traçabilité réglementaire d'officine).
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $product_id
 * @property int $batch_id
 * @property string $type
 * @property int $quantity_delta
 * @property string|null $reason
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property int|null $created_by_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PharmacyStockMovement extends Model
{
    use BelongsToCompany;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_SALE = 'sale';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_EXPIRY_WRITEOFF = 'expiry_writeoff';

    public const TYPE_RETURN = 'return';

    public const TYPES = [
        self::TYPE_RECEIPT,
        self::TYPE_SALE,
        self::TYPE_ADJUSTMENT,
        self::TYPE_EXPIRY_WRITEOFF,
        self::TYPE_RETURN,
    ];

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
        static::updating(function (): void {
            throw new LogicException('Pharmacy stock movements are append-only and can never be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Pharmacy stock movements are append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<PharmacyProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(PharmacyProduct::class, 'product_id');
    }

    /**
     * @return BelongsTo<PharmacyBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PharmacyBatch::class, 'batch_id');
    }
}
