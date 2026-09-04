<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantReceivingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Bon de réception : constate l'arrivée d'une commande fournisseur (RESTO-206, issue #6171).
 *
 * `reference` est générée automatiquement (`RCV-…`) si absente à la création.
 * `purchase_order_id` et `supplier_id` sont optionnels (réception hors commande).
 */
class RestaurantReceiving extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantReceivingFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'purchase_order_id',
        'supplier_id',
        'reference',
        'received_at',
        'note_redacted',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $receiving): void {
            if (empty($receiving->reference)) {
                $receiving->reference = self::generateReference();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'RCV-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantPurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(RestaurantPurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<RestaurantSupplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(RestaurantSupplier::class, 'supplier_id');
    }
}
