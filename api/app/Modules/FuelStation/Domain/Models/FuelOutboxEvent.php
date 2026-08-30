<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Événement d'outbox FuelStation — contrat Accounting (FUEL-015, #5809).
 *
 * Événements versionnés publiés APRÈS le commit métier, consommés de façon
 * asynchrone et idempotente. `idempotency_key` unique par tenant.
 *
 * @property int $id
 * @property string $company_id
 * @property string $event_type
 * @property string|null $aggregate_type
 * @property int|null $aggregate_id
 * @property array<string, mixed> $payload
 * @property string $status pending|processing|sent|failed
 * @property int $attempts
 * @property Carbon $available_at
 * @property string|null $last_error
 * @property Carbon|null $processed_at
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelOutboxEvent extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_outbox_events';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const MAX_ATTEMPTS = 5;

    /** Événements de contrat versionnés (contrat Accounting FUEL-015). */
    public const TYPE_SALE_RECORDED = 'fuel.sale.recorded.v1';

    public const TYPE_CASH_SESSION_CLOSED = 'fuel.cash_session.closed.v1';

    public const TYPE_DELIVERY_RECEIVED = 'fuel.delivery.received.v1';

    public const TYPE_STOCK_RECONCILED = 'fuel.stock.reconciled.v1';

    public const TYPE_INCIDENT_RESOLVED = 'fuel.incident.resolved.v1';

    public const TYPES = [
        self::TYPE_SALE_RECORDED,
        self::TYPE_CASH_SESSION_CLOSED,
        self::TYPE_DELIVERY_RECEIVED,
        self::TYPE_STOCK_RECONCILED,
        self::TYPE_INCIDENT_RESOLVED,
    ];

    protected $fillable = [
        'company_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'available_at',
        'last_error',
        'processed_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
