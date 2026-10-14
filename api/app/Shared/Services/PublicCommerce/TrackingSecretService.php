<?php

declare(strict_types=1);

namespace App\Shared\Services\PublicCommerce;

/**
 * BOS-050 (#8208) — Secret de suivi des commandes/réservations publiques.
 *
 * Mutualise le pattern prouvé par TravelTicket (SHA-256) et
 * HospitalityReservation (`tracking_code_hash`) : le suivi public d'une
 * écriture invitée se fait par une référence non énumérable + un SECRET,
 * jamais par la référence seule (anti-énumération).
 *
 * Invariants :
 *  - le secret en clair (64 hex) n'est JAMAIS persisté — seul son hash est
 *    stocké ; il est présenté UNE SEULE FOIS au client, dans la réponse de
 *    création (perdu = regénérer une écriture, pas de récupération) ;
 *  - les NOUVELLES écritures sont hachées en HMAC-SHA256 keyed par la clé
 *    applicative (#8464) : une fuite de la base ne suffit plus pour
 *    brute-forcer hors ligne un secret court (code de suivi VTC, 40 bits) ;
 *  - la vérification est DUAL-READ : HMAC d'abord, SHA-256 legacy ensuite
 *    (lignes travel #7395 / restaurant / hospitality / vtc écrites avant
 *    #8464) — aucune coupure pour les secrets déjà diffusés ;
 *  - la comparaison est timing-safe (`hash_equals`) ;
 *  - un hash stocké vide/nul ne matche jamais (fail-closed).
 */
final class TrackingSecretService
{
    /**
     * Nombre d'octets aléatoires du secret en clair (32 octets → 64 hex).
     */
    private const SECRET_BYTES = 32;

    /**
     * Génère un nouveau secret : `plain` à retourner au client une seule
     * fois, `hash` à persister en base.
     *
     * @return array{plain: string, hash: string}
     */
    public function generate(): array
    {
        $plain = bin2hex(random_bytes(self::SECRET_BYTES));

        return ['plain' => $plain, 'hash' => $this->hmac($plain)];
    }

    /**
     * Hash SHA-256 NON SALÉ (contrat historique BOS-050). Conservé pour la
     * lecture rétro-compatible des lignes écrites avant #8464 (dual-read de
     * `matches()`) — ne PLUS utiliser pour une nouvelle écriture : `hmac()`.
     */
    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Hash HMAC-SHA256 keyed par la clé applicative (#8464) — écriture de
     * tout NOUVEAU hash : sans la clé, le hash stocké ne peut pas être
     * pré-calculé (dictionnaire) ni brute-forcé hors ligne.
     */
    public function hmac(string $plain): string
    {
        return hash_hmac('sha256', $plain, $this->key());
    }

    /**
     * Le secret présenté correspond-il au hash stocké ? DUAL-READ : HMAC
     * d'abord (écritures #8464+), SHA-256 legacy ensuite — comparaison
     * timing-safe ; hash stocké vide/nul → faux (fail-closed).
     */
    public function matches(string $plain, ?string $storedHash): bool
    {
        if ($plain === '' || $storedHash === null || $storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, $this->hmac($plain))
            || hash_equals($storedHash, $this->hash($plain));
    }

    /**
     * Clé HMAC : clé applicative brute, stable par environnement — jamais
     * persistée ni exposée (config serveur uniquement).
     */
    private function key(): string
    {
        return (string) config('app.key');
    }
}
