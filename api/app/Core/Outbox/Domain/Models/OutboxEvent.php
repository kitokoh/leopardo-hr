<?php

declare(strict_types=1);

namespace App\Core\Outbox\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * MAT-008 (#5866) — Événement d'outbox générique (BC-01 PLATFORM).
 *
 * File de sortie transactionnelle : publier APRÈS le commit métier, traiter
 * de façon asynchrone via `outbox:dispatch`. Garanties : idempotence
 * (idempotency_key), retry borné avec backoff exponentiel, dead-letter
 * (status `failed`), replay contrôlé, lease anti double-traitement.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $event_type
 * @property string|null $aggregate_type
 * @property string|null $aggregate_id
 * @property array<mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property int $max_attempts
 * @property Carbon|null $available_at
 * @property Carbon|null $lease_until
 * @property string|null $last_error
 * @property string $idempotency_key
 * @property Carbon|null $processed_at
 * @mixin Builder<static>
 */
class OutboxEvent extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** Durée de lease d'un événement en cours de traitement. */
    public const LEASE_MINUTES = 15;

    /** Nombre maximal de tentatives avant dead-letter. */
    public const MAX_ATTEMPTS = 5;

    protected $table = 'outbox_events';

    protected $fillable = [
        'company_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'available_at',
        'lease_until',
        'last_error',
        'idempotency_key',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'available_at' => 'datetime',
        'lease_until' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Backoff exponentiel (secondes) pour la tentative donnée, avec jitter.
     */
    public static function backoffForAttempt(int $attempt): int
    {
        // 30s, 2 min, 8 min, 32 min, 2 h… borné à 4 h.
        $base = (int) min(30 * (4 ** max(0, $attempt - 1)), 4 * 3600);

        return $base + random_int(0, min($base, 60));
    }
}
