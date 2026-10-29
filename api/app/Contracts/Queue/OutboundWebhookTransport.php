<?php

declare(strict_types=1);

namespace App\Contracts\Queue;

use App\Shared\DTOs\OutboundWebhookResponse;

/**
 * BOS-021 (#8210) — contrat unique de TRANSPORT des webhooks sortants
 * (BC-14 INTEGRATION).
 *
 * L'issue constate que « chaque nouvelle verticale transactionnelle recopie
 * l'une d'elles (endpoints, signature, livraison, retry) ». Le palier 1 a
 * mutualisé la **primitive de signature** ; ce contrat mutualise la
 * **livraison HTTP** : l'appel sortant, le timeout, la garde anti-SSRF et la
 * mesure de durée.
 *
 * Ce qui reste délibérément chez le producteur :
 *
 *   - **la chaîne signée** (Svix `{timestamp}.{body}` pour Billing, payload
 *     canonique `ksort` pour Travel) — elle fait partie de son contrat public ;
 *   - **les en-têtes de signature** (`Webhook-Signature` vs
 *     `X-Leopardo-Signature`) — les renommer casserait les consommateurs ;
 *   - **le journal de livraison et la politique de retry** — Billing s'appuie
 *     sur les `tries`/`backoff` de la queue Laravel, Travel sur un backoff
 *     persisté en base (`next_attempt_at`). Les deux sont corrects pour leur
 *     volume ; les fusionner imposerait une migration de données sans gain.
 *
 * Ce qui est unifié ici est exactement ce qui était recopié à l'identique et
 * qui n'a aucune raison de diverger : la façon dont on frappe un endpoint
 * tiers, et la garde qui empêche de frapper un endpoint interne.
 *
 * @see \App\Jobs\Services\HttpOutboundWebhookTransport
 */
interface OutboundWebhookTransport
{
    /**
     * Livre un corps déjà sérialisé et déjà signé.
     *
     * @param  string  $url  URL absolue `https://` de l'endpoint.
     * @param  string  $body  Corps exact qui sera envoyé (et qui a été signé).
     * @param  array<string, string>  $headers  En-têtes du producteur, signature incluse.
     *
     * @throws \App\Shared\Exceptions\OutboundWebhookBlockedException quand l'URL
     *                                                                n'est pas `https://` ou résout vers un hôte privé/réservé.
     */
    public function send(string $url, string $body, array $headers): OutboundWebhookResponse;
}
