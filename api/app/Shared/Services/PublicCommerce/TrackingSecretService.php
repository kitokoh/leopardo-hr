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
 *  - le secret en clair (64 hex) n'est JAMAIS persisté — seul son hash
 *    SHA-256 est stocké ; il est présenté UNE SEULE FOIS au client, dans la
 *    réponse de création (perdu = regénérer une écriture, pas de récupération) ;
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

        return ['plain' => $plain, 'hash' => $this->hash($plain)];
    }

    /**
     * Hash SHA-256 du secret présenté (stockage et comparaison).
     */
    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Le secret présenté correspond-il au hash stocké ? Comparaison
     * timing-safe ; hash stocké vide/nul → faux (fail-closed).
     */
    public function matches(string $plain, ?string $storedHash): bool
    {
        if ($plain === '' || $storedHash === null || $storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, $this->hash($plain));
    }
}
