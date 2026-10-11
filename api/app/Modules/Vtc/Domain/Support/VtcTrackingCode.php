<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Support;

/**
 * Code de suivi public d'une course VTC (#8423, M-13/M-14).
 *
 * Format : 8 symboles tirés de l'alphabet NON AMBIGU de TravelTicket
 * (#7395 — ni 0/O, ni 1/I/L) : le code est recopié ou dicté à la main par
 * un passager (pattern suivi public par référence + code de la
 * marketplace travel). 32 symboles — 256 est un multiple de 32, tirage
 * `random_int` sans biais modulo (40 bits d'entropie ; l'énumération en
 * ligne est rendue impraticable par le bucket `shop-public`, la
 * comparaison timing-safe et le 404 uniforme).
 *
 * Seul le hash SHA-256 du code est persisté (`vtc_rides.tracking_code_hash`
 * via `TrackingSecretService`, socle BOS-050) — le code en clair n'est
 * présenté qu'UNE SEULE FOIS, dans la réponse de création de la
 * réservation invitée.
 */
final class VtcTrackingCode
{
    /**
     * Alphabet sans caractères ambigus (ni 0/O, ni 1/I/L) — identique à
     * TravelTicket::VALIDATION_CODE_ALPHABET (#7395).
     */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** Longueur utile du code (plan M-14 : « string ~8 chars »). */
    private const LENGTH = 8;

    /**
     * Génère un nouveau code de suivi en clair (à présenter une seule fois
     * au passager — jamais persisté).
     */
    public static function generate(): string
    {
        $code = '';
        $maxIndex = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $code;
    }

    /**
     * Normalise un code présenté par le client (majuscules, espaces
     * trimmés) avant comparaison de hash — la génération est déjà en
     * majuscules, la saisie manuelle ne l'est pas forcément.
     */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }
}
