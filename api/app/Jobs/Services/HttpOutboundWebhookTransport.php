<?php

declare(strict_types=1);

namespace App\Jobs\Services;

use App\Contracts\Queue\OutboundWebhookTransport;
use App\Rules\NotPrivateUrl;
use App\Shared\DTOs\OutboundWebhookResponse;
use App\Shared\Exceptions\OutboundWebhookBlockedException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * BOS-021 (#8210) — implémentation unique du transport des webhooks sortants.
 *
 * Avant ce lot, deux endroits frappaient un endpoint tiers :
 *
 *   - `DispatchWebhook` (Billing) : `Http::timeout(10)->withHeaders(...)->post()`,
 *     avec une garde anti-SSRF **inline** (re-résolution DNS juste avant l'appel) ;
 *   - `TravelWebhookDispatcher` (Travel) : `Http::withoutVerifying()->send()`,
 *     **sans aucune garde anti-SSRF**.
 *
 * La seconde est le vrai défaut que ce lot corrige. `withoutVerifying()`
 * désactive la vérification du certificat TLS : un abonné Travel pouvait donc
 * enregistrer une URL `https://` pointant vers un hôte interne (le
 * `StoreTravelWebhookSubscriptionRequest` ne validait que `url`, pas
 * `NotPrivateUrl`), et le dispatcher lui envoyait le payload signé — avec, en
 * prime, un certificat non vérifié, donc un serveur imposteur accepté.
 *
 * Le transport applique donc, pour TOUS les producteurs :
 *
 *   1. **`https://` obligatoire** — un webhook signé en clair est un secret
 *      qui voyage en clair ;
 *   2. **re-résolution DNS juste avant l'appel** (`NotPrivateUrl::isPublicHost`)
 *      — la validation à la création ne suffit pas, le DNS peut être rebindé
 *      entre-temps (TOCTOU) ;
 *   3. **vérification TLS active** — plus de `withoutVerifying()` ;
 *   4. **timeout borné** et mesure de durée, pour que le producteur journalise
 *      une latence réelle plutôt qu'une estimation.
 *
 * Le transport ne lève PAS sur un statut non-2xx : il rapporte. Un `500`
 * partenaire est une information, pas une exception de transport — c'est le
 * producteur qui décide de réessayer (Billing rethrow pour la queue, Travel
 * incrémente son backoff persisté).
 */
final class HttpOutboundWebhookTransport implements OutboundWebhookTransport
{
    /**
     * Timeout par tentative, en secondes.
     *
     * 10 s est la valeur historique de Billing. Travel utilisait 8 s ; aligner
     * sur 10 s ne peut que réduire les faux échecs (un partenaire lent mais
     * sain), et le retry reste borné par la politique du producteur.
     */
    public const TIMEOUT_SECONDS = 10;

    public function send(string $url, string $body, array $headers): OutboundWebhookResponse
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! str_starts_with($url, 'https://') || ! is_string($host) || ! NotPrivateUrl::isPublicHost($host)) {
            throw OutboundWebhookBlockedException::forUrl($url);
        }

        $start = microtime(true);

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders($headers)
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable $exception) {
            // Une erreur réseau est transitoire : on la laisse remonter pour
            // que le producteur applique SA politique de retry. La convertir
            // en réponse `0` ferait passer un incident réseau pour un refus
            // partenaire, et Billing cesserait de réessayer.
            throw $exception;
        }

        return new OutboundWebhookResponse(
            status: $response->status(),
            body: mb_substr($response->body(), 0, 2000),
            durationMs: (int) ((microtime(true) - $start) * 1000),
        );
    }
}
