<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\PaymentProvider;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelPaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Paiement d'une réservation (TRAVEL-210, issue #6023).
 *
 * `callback_payload_redacted` : payload webhook provider expurgé de tout
 * secret/token avant persistance (jamais de credential en clair, cf.
 * pattern Accounting/Billing HMAC). `idempotency_key` unique par tenant.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property string $booking_id
 * @property string $advert_id
 * @property PaymentProvider $provider_code
 * @property int $amount_minor
 * @property string $currency
 * @property PaymentStatus $status
 * @property string $provider_reference
 * @property array<string, mixed> $callback_payload_redacted
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelPayment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'booking_id',
        'advert_id',
        'provider_code',
        'amount_minor',
        'currency',
        'status',
        'provider_reference',
        'callback_payload_redacted',
        'idempotency_key',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'provider_code' => PaymentProvider::class,
        'status' => PaymentStatus::class,
        'callback_payload_redacted' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->reference)) {
                $payment->reference = self::generateReference();
            }

            if (empty($payment->idempotency_key)) {
                $payment->idempotency_key = (string) Str::uuid();
            }
        });
    }

    public static function generateReference(): string
    {
        return 'PAY-'.strtoupper(Str::random(10));
    }

    /**
     * @return BelongsTo<TravelBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TravelBooking::class, 'booking_id');
    }

    /**
     * @return BelongsTo<TravelAdvert, $this>
     */
    public function advert(): BelongsTo
    {
        return $this->belongsTo(TravelAdvert::class, 'advert_id');
    }
}
