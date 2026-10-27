<?php

declare(strict_types=1);

namespace App\Contracts\Queue;

/**
 * BOS-021 (#8210) — contrat unique de signature des webhooks SORTANTS.
 *
 * Avant cette interface, chaque verticale transactionnelle recopiait sa propre
 * primitive HMAC : `Billing\Infrastructure\Services\WebhookEnvelopeBuilder`
 * (schéma Svix `v1=…,t=…`) et
 * `TravelAgency\Infrastructure\Services\TravelWebhookSigner`
 * (en-têtes `X-Travel-*`). Deux implémentations, deux endroits où se tromper
 * sur la comparaison en temps constant.
 *
 * Le contrat porte UNIQUEMENT la primitive cryptographique : HMAC-SHA256 sur
 * une chaîne déjà sérialisée, et comparaison en temps constant. La
 * construction de la chaîne signée reste la responsabilité du producteur,
 * parce qu'elle fait partie de son contrat public :
 *
 *   - Billing signe `"{timestamp}.{rawJsonBody}"` (schéma Svix, vérifiable
 *     par les bibliothèques partenaires standard) ;
 *   - Travel signe le payload canonique (`ksort` + `json_encode`) sans
 *     préfixe d'horodatage, l'horodatage voyageant dans un en-tête séparé.
 *
 * Unifier la primitive sans unifier la chaîne signée est ce qui permet de
 * dédupliquer le code SANS casser un seul consommateur existant : les
 * signatures produites restent identiques octet pour octet (prouvé par
 * `OutboundWebhookSignatureUnificationTest`).
 *
 * @see \App\Jobs\Services\HmacWebhookSigner
 */
interface OutboundWebhookSigner
{
    /**
     * Signe une chaîne déjà sérialisée par le producteur.
     *
     * @param  string  $secret  Secret partagé avec le consommateur (jamais loggé).
     * @param  string  $signedPayload  Chaîne exacte qui sera signée.
     */
    public function sign(string $secret, string $signedPayload): string;

    /**
     * Vérifie une signature en temps constant (anti timing-attack).
     *
     * `hash_equals` est obligatoire : une comparaison `===` fuite la position
     * du premier octet divergent et permet de reconstruire une signature
     * octet par octet.
     */
    public function verify(string $secret, string $signedPayload, string $providedSignature): bool;
}
