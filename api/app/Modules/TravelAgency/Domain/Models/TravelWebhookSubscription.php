<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor;
use App\Modules\TravelAgency\Domain\Enums\TravelWebhookEvent;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelWebhookSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * TRAVEL-806 (#6097) — Abonnement webhook d'un transporteur.
 *
 * Le secret HMAC n'est JAMAIS exposé en clair : chiffré au repos via
 * `SensitiveDataEncryptor`, l'API ne renvoie que `has_secret`.
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $carrier_id
 * @property string $name
 * @property string $url
 * @property string $secret_encrypted
 * @property array<int, string> $events
 * @property bool $active
 */
class TravelWebhookSubscription extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelWebhookSubscriptionFactory> */
    use HasFactory;

    protected $table = 'travel_webhook_subscriptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'carrier_id',
        'name',
        'url',
        'secret_encrypted',
        'events',
        'active',
    ];

    protected $casts = [
        'events' => 'array',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            /** @var string|null $id */
            $id = $subscription->id;
            if ($id === null) {
                $subscription->id = (string) Str::uuid();
            }
        });
    }

    public function setSecret(string $plainSecret): void
    {
        $this->secret_encrypted = app(SensitiveDataEncryptor::class)->encrypt($plainSecret);
    }

    public function secret(): string
    {
        return app(SensitiveDataEncryptor::class)->decrypt($this->secret_encrypted);
    }

    public function supports(string $eventType): bool
    {
        return $this->active === true && in_array($eventType, $this->events ?? [], true);
    }

    /** @return list<string> */
    public static function supportedEvents(): array
    {
        return TravelWebhookEvent::values();
    }
}
