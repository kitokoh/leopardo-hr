<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $webhook_endpoint_id
 * @property string|null $event
 * @property array<mixed> $payload
 * @property string|null $response_code
 * @property string|null $response_body
 * @property int $duration_ms
 * @property Carbon|null $dead_lettered_at
 *
 * @mixin Builder<static>
 */
class WebhookDelivery extends Model
{
    public $timestamps = false;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'webhook_endpoint_id',
        'event',
        'payload',
        'response_code',
        'response_body',
        'duration_ms',
        'dead_lettered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'dead_lettered_at' => 'datetime',
    ];

    /** @return BelongsTo<WebhookEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeDeadLettered(Builder $q): Builder
    {
        return $q->whereNotNull('dead_lettered_at');
    }
}
