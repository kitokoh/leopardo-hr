<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Core\Auth\Infrastructure\Services\SensitiveDataEncryptor;
use App\Modules\TravelAgency\Domain\Models\TravelWebhookSubscription;

/**
 * TRAVEL-806 (#6097) — cycle de vie du secret HMAC des abonnements webhook.
 *
 * Le secret n'est JAMAIS stocké ni exposé en clair : chiffré au repos via
 * `SensitiveDataEncryptor` (colonne `secret_encrypted`), l'API ne renvoie que
 * `has_secret` / `secret_prefix` à la création.
 *
 * Classe d'Infrastructure : le modèle de Domain ne manipule pas de chiffrement
 * (pureté des couches, issue #6568).
 */
final class TravelWebhookSecretService
{
    public function __construct(private readonly SensitiveDataEncryptor $encryptor)
    {
    }

    /** Chiffre et pose le secret sur le modèle (persistance à la charge de l'appelant). */
    public function set(TravelWebhookSubscription $subscription, string $plainSecret): void
    {
        $subscription->secret_encrypted = $this->encryptor->encrypt($plainSecret);
    }

    /** Déchiffre le secret stocké. */
    public function get(TravelWebhookSubscription $subscription): string
    {
        return $this->encryptor->decrypt((string) $subscription->secret_encrypted);
    }

    /** Préfixe court et stable du secret (affichage UI, jamais le secret lui-même). */
    public function prefix(TravelWebhookSubscription $subscription): string
    {
        return substr(hash('sha256', $this->get($subscription)), 0, 8);
    }
}
