<?php

declare(strict_types=1);

namespace App\Shared\Services;

/**
 * Issue #5740 (CRM PRE) — finaliser le threat model des webhooks et
 * intégrations.
 *
 * Primitives de vérification d'un webhook ENTRANT, traité comme une frontière
 * hostile. Chaque contrôle est une fonction pure, testable unitairement, et
 * composée par les contrôleurs entrants (Stripe, Chargily, email-bounce,
 * marketing-lead, futurs providers WhatsApp/SMS/CRM).
 *
 * Contrat de sécurité (docs/security/WEBHOOK_THREAT_MODEL.md) :
 *   1. L'authenticité est établie par signature (HMAC) ou secret partagé
 *      (hash_equals) AVANT tout traitement — jamais par un champ du payload.
 *   2. Une fenêtre de rejeu (horodatage) borne la validité d'un message.
 *   3. La taille et la forme (JSON) du payload sont bornées avant parse.
 *   4. Le fournisseur/la connexion source est allowlistée.
 *   5. Le secret vit dans la configuration (jamais en base, logs, fixtures).
 *
 * Aucun tenant n'est jamais déduit d'un champ non authentifié : la résolution
 * du tenant n'a lieu qu'APRÈS la vérification (fail-closed), voir
 * OnlinePaymentService pour le pattern withinTenant() post-verif.
 */
final class InboundWebhookVerifier
{
    /** Fenêtre de rejeu par défaut (secondes) — alignée sur Stripe (300 s). */
    public const DEFAULT_TIMESTAMP_WINDOW_SECONDS = 300;

    /** Taille maximale de payload par défaut (1 MiB). */
    public const DEFAULT_MAX_PAYLOAD_BYTES = 1_048_576;

    /**
     * Compare deux secrets en temps constant (anti timing-attack).
     */
    public static function secretMatches(string $configured, string $provided): bool
    {
        return hash_equals($configured, $provided);
    }

    /**
     * Extrait l'horodatage Unix d'un en-tête. Retourne null si absent ou
     * invalide — le contrôleur décide alors du mode (optionnel vs requis).
     */
    public static function timestampFromHeader(?string $header): ?int
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $ts = filter_var(trim($header), FILTER_VALIDATE_INT);

        return is_int($ts) ? $ts : null;
    }

    /**
     * Vérifie que l'horodatage est dans la fenêtre de rejeu.
     *
     * @param  int|null  $windowSeconds  null = DEFAULT_TIMESTAMP_WINDOW_SECONDS
     */
    public static function timestampIsFresh(int $timestamp, ?int $windowSeconds = null): bool
    {
        $window = $windowSeconds ?? self::DEFAULT_TIMESTAMP_WINDOW_SECONDS;
        $now = time();

        return $timestamp > 0
            && $now - $timestamp <= $window
            && $timestamp - $now <= $window;
    }

    /**
     * Vérifie la signature HMAC-SHA256 d'un payload brut (schéma Svix).
     *
     * Signature attendue : sha256("<timestamp>.<rawBody>") avec le secret.
     */
    public static function verifyHmacSignature(string $secret, string $signature, string $rawBody, int $timestamp): bool
    {
        if ($secret === '' || $signature === '' || $timestamp <= 0) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Vérifie que le payload brut respecte la taille maximale.
     *
     * @param  int|null  $maxBytes  null = DEFAULT_MAX_PAYLOAD_BYTES
     */
    public static function payloadWithinLimit(string $rawBody, ?int $maxBytes = null): bool
    {
        return strlen($rawBody) <= ($maxBytes ?? self::DEFAULT_MAX_PAYLOAD_BYTES);
    }

    /**
     * Vérifie que le payload est un JSON valide (non vide).
     */
    public static function isJsonPayload(string $rawBody): bool
    {
        if (trim($rawBody) === '') {
            return false;
        }

        try {
            json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Vérifie que la source déclarée appartient à l'allowlist des providers
     * connus. La source est un identifiant de CONNEXION configuré côté
     * serveur, jamais une valeur du payload.
     *
     * @param  array<int, string>  $knownProviders
     */
    public static function isKnownProvider(string $source, array $knownProviders): bool
    {
        return in_array($source, $knownProviders, true);
    }
}
