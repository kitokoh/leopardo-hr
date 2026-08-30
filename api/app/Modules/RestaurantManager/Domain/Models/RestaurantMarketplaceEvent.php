<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * RESTO-806 (#6227) — Événement marketplace (webhook entrant/sortant).
 *
 * Journal d'audit et support d'idempotence des intégrations apps de
 * livraison : un même `event_id` provider ne crée jamais deux commandes.
 * `payload_redacted` ne conserve que les champs métier (audit RGPD).
 */
class RestaurantMarketplaceEvent extends Model
{
    use BelongsToCompany;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'provider',
        'event_id',
        'event_type',
        'idempotency_key',
        'status',
        'payload_redacted',
        'last_error',
        'order_reference',
        'processed_at',
    ];

    protected $casts = [
        'payload_redacted' => 'array',
        'processed_at' => 'datetime',
    ];
}
