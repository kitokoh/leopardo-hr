<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Événement d'outbox FuelStation (FUEL-015, issue #5809).
 *
 * Contrat Accounting : agrégats validés publiés après commit (jamais dans
 * la transaction métier), consommés de façon asynchrone et idempotente par
 * `fuel:outbox-dispatch`. Statuts pending/processing/sent/failed ;
 * `available_at` porte le backoff ; attempts borne le dead-letter.
 *
 * @property int $id
 * @property string $company_id
 * @property string $event_type
 * @property string|null $aggregate_type
 * @property string|null $aggregate_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $available_at
 * @property string|null $last_error
 * @property Carbon|null $processed_at
 * @property string $idempotency_key
 *
 * @mixin Builder<static>
 */
class FuelOutboxEvent extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Nombre maximal de tentatives avant dead-letter. */
    public const MAX_ATTEMPTS = 5;

    protected $table = 'fuel_outbox_events';

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

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
