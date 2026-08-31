<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Modules\TravelAgency\Domain\Enums\TravelWebhookDeliveryStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * TRAVEL-806 (#6097) — Livraison de webhook (journal idempotent).
 *
 * Une livraison par (subscription, outbox_event_id) : le rejeu d'un même
 * événement ne crée jamais de doublon. Payload redigée (jamais de secret,
 * jamais de PII en clair).
 *
 * @property string $id
 * @property string $company_id
 * @property string $subscription_id
 * @property string|null $outbox_event_id
 * @property string $event_type
 * @property array<string, mixed> $payload_redacted
 * @property TravelWebhookDeliveryStatus $status
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property int|null $last_status_code
 * @property string|null $last_error
 * @property Carbon|null $delivered_at
 */
class TravelWebhookDelivery extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelWebhookDeliveryFactory> */
    use HasFactory;

    protected $table = 'travel_webhook_deliveries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'subscription_id',
        'outbox_event_id',
        'event_type',
        'payload_redacted',
        'status',
        'attempts',
        'next_attempt_at',
        'last_status_code',
        'last_error',
        'delivered_at',
    ];

    protected $casts = [
        'payload_redacted' => 'array',
        'status' => TravelWebhookDeliveryStatus::class,
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'last_status_code' => 'integer',
        'delivered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $delivery): void {
            /** @var string|null $id */
            $id = $delivery->id;
            if ($id === null) {
                $delivery->id = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<TravelWebhookSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TravelWebhookSubscription::class, 'subscription_id');
    }

    public function isDue(): bool
    {
        return $this->status === TravelWebhookDeliveryStatus::PENDING
            || ($this->status === TravelWebhookDeliveryStatus::FAILED
                && $this->next_attempt_at !== null
                && $this->next_attempt_at->isPast());
    }

    /** Backoff exponentiel borné : 1 min, 2, 4, 8… plafonné à 30 min. */
    public function scheduleRetry(): void
    {
        $delayMinutes = min(30, 2 ** max(0, $this->attempts - 1));
        $this->next_attempt_at = now()->addMinutes($delayMinutes);
    }
}
