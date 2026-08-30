<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * #5813 (FUEL-019) — Événement d'outbox FuelStation (tenant-scoped).
 *
 * Structure identique à `CrmOutboxEvent` (#5741) / `RestaurantOutboxEvent` :
 * persistance après commit, consommation asynchrone idempotente.
 *
 * @property int $id
 * @property string $company_id
 * @property string $event_type
 * @property array<mixed>|null $payload_redacted
 * @property string $status
 * @property int $attempts
 * @property \Illuminate\Support\Carbon|null $available_at
 * @property string|null $last_error
 * @property string $idempotency_key
 */
class FuelOutboxEvent extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<\Database\Factories\FuelOutboxEventFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    protected $table = 'fuel_outbox_events';

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_redacted' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
        ];
    }
}
