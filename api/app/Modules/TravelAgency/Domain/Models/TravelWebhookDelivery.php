<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Livraison d'un webhook transporteur (TRAVEL-806, issue #6097).
 *
 * Rejouable sans doublon : `unique(subscription_id, event_id)` — un rejeu
 * de l'outbox ne crée jamais une seconde livraison. Erreur transitoire →
 * retry avec backoff (`next_attempt_at`) ; permanente ou attempts ≥ max →
 * dead-letter (status failed).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $subscription_id
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed> $payload_redacted
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 * @property int $last_http_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelWebhookDelivery extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const MAX_ATTEMPTS = 5;

    protected $fillable = [
        'company_id',
        'subscription_id',
        'event_id',
        'event_type',
        'payload_redacted',
        'status',
        'attempts',
        'next_attempt_at',
        'last_http_status',
    ];

    protected $casts = [
        'payload_redacted' => 'array',
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'last_http_status' => 'integer',
    ];
}
