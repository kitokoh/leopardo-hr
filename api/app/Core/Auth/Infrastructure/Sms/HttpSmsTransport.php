<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Sms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Transport HTTP reel du canal SMS (#9091).
 *
 * **Pourquoi une classe a part.** `LogOnlySmsCodeSender` porte la politique du
 * canal (E.164, quota, masquage des traces) ; le transport ne porte que
 * l'appel reseau. Les separer rend le transport remplacable (un autre
 * fournisseur = une autre implementation) sans toucher a la politique, et
 * testable en interceptant `Http::fake()`.
 *
 * **Contrat de retour.** `deliver()` rend `true` UNIQUEMENT si le fournisseur a
 * repondu 2xx. Toute autre issue (timeout, 4xx, 5xx, exception reseau) rend
 * `false` : l'appelant doit alors considerer que RIEN n'est parti.
 */
final class HttpSmsTransport
{
    /**
     * Emet l'appel HTTP d'envoi. `true` = le fournisseur a accepte le message.
     */
    public function deliver(
        string $endpoint,
        string $apiKey,
        string $from,
        string $e164,
        string $code,
        string $locale,
    ): bool {
        try {
            $response = Http::withToken($apiKey)
                ->asJson()
                ->acceptJson()
                ->timeout(10)
                ->post($endpoint, [
                    'to' => $e164,
                    'from' => $from,
                    'text' => $code,
                    'locale' => $locale,
                ]);
        } catch (Throwable) {
            return false;
        }

        return $response->successful();
    }
}
