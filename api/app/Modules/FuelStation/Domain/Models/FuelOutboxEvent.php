<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Outbox des événements FuelStation (Accounting + notifications)
 * — FUEL-015 (issue #5809), FUEL-019 (issue #5813).
 *
 * Même contrat que `CrmOutboxEvent` (#5741) : persistance après commit,
 * consommation asynchrone idempotente par `fuel:outbox-dispatch`.
 *
 * @property int $id
 * @property string $company_id
 * @property string $event_type
 * @property string|null $aggregate_type
 * @property string|null $aggregate_id
 * @property array<string, mixed> $payload
 * @property string $status pending|processing|sent|failed
 * @property int $attempts
 * @property Carbon $available_at
 * @property string|null $last_error
 * @property Carbon|null $processed_at
 * @property string $idempotency_key
 * @property Carbon $created_at
 * @property Carbon $updated_at
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

    public const MAX_ATTEMPTS = 8;

    // Événements versionnés — contrat docs/contracts/fuel-accounting.md.
    public const EVENT_SALE_RECORDED = 'fuel.sale.recorded.v1';

    public const EVENT_CASH_SESSION_CLOSED = 'fuel.cash_session.closed.v1';

    public const EVENT_STOCK_RECONCILED = 'fuel.stock.reconciled.v1';

    public const EVENT_INCIDENT_REPORTED = 'fuel.incident.reported.v1';

    public const EVENT_STOCK_THRESHOLD_BREACHED = 'fuel.stock.threshold.breached.v1';

    public const EVENT_CUSTOMER_CREATED = 'fuel.customer.created.v1';

    public const EVENT_CUSTOMER_CONSENT_UPDATED = 'fuel.customer.consent.updated.v1';

    public const EVENT_SYNC_READINGS_RECEIVED = 'fuel.sync.readings.received.v1';

    public const EVENT_SYNC_SALES_RECEIVED = 'fuel.sync.sales.received.v1';

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
