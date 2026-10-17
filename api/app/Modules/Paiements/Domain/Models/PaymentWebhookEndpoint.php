<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * BC-38 PAIEMENTS (#8688) — point d'entree webhook d'une instance.
 *
 * Le `token` identifie l'instance **sans ambiguite** : c'est lui, et rien
d'autre, qui route un webhook entrant. Ni le payload, ni le nom du fournisseur
ne doivent servir a deviner qui appelle — un payload est controle par
l'emetteur, un token est un secret partage.
 *
 * `secret_signature` est chiffre at-rest et n'est jamais expose.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $provider_instance_id
 * @property string $token
 * @property string $path
 * @property string|null $secret_signature
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PaymentWebhookEndpoint extends Model
{
    protected $table = 'payment_webhook_endpoints';

    protected $fillable = [
        'company_id',
        'provider_instance_id',
        'token',
        'path',
        'secret_signature',
        'status',
    ];

    protected $casts = [
        'secret_signature' => 'encrypted',
    ];

    /** @return BelongsTo<PaymentProviderInstance, $this> */
    public function providerInstance(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderInstance::class, 'provider_instance_id');
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
