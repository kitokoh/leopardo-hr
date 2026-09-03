<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lien de suivi public borné (DELIVERY-204, issue #6288).
 *
 * Le token (64 caractères aléatoires, indexé unique) EST la credential :
 * résolution O(1) sans scope tenant (pattern AccountingDocumentShare #5225),
 * expiration courte, anti-énumération. Pas de PII au-delà de la livraison.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $delivery_id
 * @property string $share_token
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property-read Delivery|null $delivery
 *
 * @mixin Builder<static>
 */
class DeliveryTrackingShare extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_tracking_shares';

    protected $fillable = [
        'company_id',
        'delivery_id',
        'share_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
