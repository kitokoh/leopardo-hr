<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\PaymentProvider;
use App\Modules\RestaurantManager\Domain\Enums\PaymentStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantOrderPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Paiement d'une commande (RESTO-209, issue #6174).
 *
 * `idempotency_key` (uuid) est générée si absente à la création — unique par
 * tenant pour absorber les doubles soumissions. `callback_payload_redacted`
 * stocke la réponse brute du prestataire (sans données sensibles).
 */
class RestaurantOrderPayment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantOrderPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'pos_session_id',
        'provider_code',
        'amount_minor',
        'currency',
        'status',
        'paid_at',
        'provider_reference',
        'tip_minor',
        'callback_payload_redacted',
        'idempotency_key',
    ];

    protected $casts = [
        'provider_code' => PaymentProvider::class,
        'amount_minor' => 'integer',
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
        'tip_minor' => 'integer',
        'callback_payload_redacted' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->idempotency_key)) {
                $payment->idempotency_key = (string) Str::uuid();
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

    /**
     * @return BelongsTo<RestaurantPosSession, $this>
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(RestaurantPosSession::class, 'pos_session_id');
    }
}
