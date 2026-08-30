<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelOutboxEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property Carbon|null $processed_at
 * @property string $idempotency_key
 *
 * @mixin Builder<static>
 */
class TravelOutboxEvent extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelOutboxEventFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    /** Réservé pendant le traitement (lease de worker, cf. #5741). */
    public const STATUS_PROCESSING = 'processing';

    /** Consommé avec succès (équivalent `sent` du pattern CRM). */
    public const STATUS_PUBLISHED = 'published';

    /** Dead-letter après MAX_ATTEMPTS ou erreur permanente. */
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
        'processed_at',
        'idempotency_key',
    ];

    protected $casts = [
        'payload_redacted' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
