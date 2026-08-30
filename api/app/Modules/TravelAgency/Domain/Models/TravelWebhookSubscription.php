<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * Abonnement webhook d'un transporteur (TRAVEL-806, issue #6097).
 *
 * `secret_hash` stocke un hash du secret de signature HMAC — jamais le
 * secret en clair (les réponses API n'exposent que le hash partiel).
 * `events` = liste des `travel.*.v1` auxquels le transporteur s'abonne.
 */
class TravelWebhookSubscription extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'carrier_id',
        'url',
        'secret_encrypted',
        'events',
        'active',
        'created_by_user_id',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Secret déchiffré (usage interne : signature HMAC uniquement).
     */
    public function secret(): string
    {
        return Crypt::decryptString((string) $this->secret_encrypted);
    }

    /**
     * Préfixe de hash exposable (vérification manuelle, jamais le secret).
     */
    public function secretPrefix(): string
    {
        return substr(hash('sha256', $this->secret()), 0, 8);
    }

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class, 'carrier_id');
    }

    public function subscribesTo(string $eventType): bool
    {
        return $this->active && in_array($eventType, $this->events ?? [], true);
    }
}
