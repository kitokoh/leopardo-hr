<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string|null $url
 * @property array<mixed> $events
 * @property string|null $secret
 * @property bool $active
 * @property string|null $failure_count
 * @property Carbon|null $last_triggered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookEndpoint extends Model
{
    use BelongsToCompany;

    protected $table = 'webhook_endpoints';

    protected $fillable = [
        'company_id',
        'url',
        'events',
        'secret',
        'active',
        'failure_count',
        'last_triggered_at',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_endpoint_id');
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeListeningTo(Builder $q, string $event): Builder
    {
        return $q->whereJsonContains('events', $event);
    }
}
