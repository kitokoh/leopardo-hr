<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notification destinataire (DELIVERY-206, issue #6290) — outbox tenant-scoped.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $delivery_id
 * @property string $event_type
 * @property string $channel
 * @property string $recipient_phone
 * @property string $template_key
 * @property string $status
 * @property int $attempts
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property-read Delivery|null $delivery
 *
 * @mixin Builder<static>
 */
class DeliveryNotification extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_notifications';

    protected $fillable = [
        'company_id',
        'delivery_id',
        'event_type',
        'channel',
        'recipient_phone',
        'template_key',
        'status',
        'attempts',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'int',
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }
}
