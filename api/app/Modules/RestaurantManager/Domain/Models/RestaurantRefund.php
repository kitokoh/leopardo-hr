<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\RefundStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Remboursement d'une commande (RESTO-209, issue #6174).
 *
 * `idempotency_key` (uuid) est générée si absente à la création — unique par
 * tenant. `payment_id` optionnel : le remboursement peut porter sur un
 * paiement précis ou être global à la commande.
 */
class RestaurantRefund extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantRefundFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'payment_id',
        'amount_minor',
        'reason_code',
        'reason_text_redacted',
        'refunded_by_user_id',
        'status',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'status' => RefundStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $refund): void {
            if (empty($refund->idempotency_key)) {
                $refund->idempotency_key = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }
}
