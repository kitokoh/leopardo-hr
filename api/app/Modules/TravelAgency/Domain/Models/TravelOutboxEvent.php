<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #6024 (TRAVEL-211) — Événement d'outbox TravelAgency (tenant-scoped).
 *
 * Structure identique à `App\Modules\CRM\Domain\Models\CrmOutboxEvent`
 * (#5741) : persistance après commit, consommation asynchrone idempotente,
 * replay sans perte ni doublon.
 *
 * @property int $id
 * @property string $company_id
 * @property string $event_type
 * @property array<mixed> $payload_redacted
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $available_at
 * @property string|null $last_error
 * @property string $idempotency_key
 *
 * @mixin Builder<static>
 */
class TravelOutboxEvent extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    /** Nombre maximal de tentatives avant dead-letter. */
    public const MAX_ATTEMPTS = 5;

    protected $table = 'travel_outbox_events';

    protected $fillable = [
        'company_id',
        'event_type',
        'payload_redacted',
        'status',
        'attempts',
        'available_at',
        'last_error',
        'idempotency_key',
    ];

    protected $casts = [
        'payload_redacted' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
    ];
}
