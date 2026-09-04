<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\PurchaseOrderStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantPurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Bon de commande fournisseur (RESTO-206, issue #6171).
 *
 * `reference` est générée automatiquement (`PO-…`) si absente à la création.
 * `total_minor` est la somme des lignes (minor units) ; `expected_at` et
 * `received_at` bornent le cycle réception.
 */
class RestaurantPurchaseOrder extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantPurchaseOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'supplier_id',
        'reference',
        'status',
        'expected_at',
        'received_at',
        'total_minor',
        'currency',
    ];

    protected $casts = [
        'status' => PurchaseOrderStatus::class,
        'expected_at' => 'datetime',
        'received_at' => 'datetime',
        'total_minor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            if (empty($order->reference)) {
                $order->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'PO-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(RestaurantSupplier::class, 'supplier_id');
    }

    /**
     * @return HasMany<RestaurantPurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantPurchaseOrderItem::class, 'purchase_order_id');
    }
}
