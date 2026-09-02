<?php

declare(strict_types=1);

namespace App\Core\Auth\Infrastructure\Services;

/**
 * Issue #5713 — CRM V0 : lookup PII irréversible (HMAC) + masquage.
 *
 * Stratégie documentée dans docs/security/CRM_PII_HMAC.md :
 *
 * - **Recherche exacte sans stockage en clair** : l'email/téléphone d'un
 *   lead/contact est stocké chiffré (AES-256, SensitiveDataEncryptor) et
 *   indexé par un digest HMAC-SHA256 déterministe. Une recherche par
 *   email/téléphone hache l'entrée et fait une égalité sur la colonne
 *   `*_hmac` — aucune valeur PII en clair ne transite dans un index.
 * - **Clé dérivée** : `hash_hmac('sha256', self::KEY_SALT, config('app.key'))`
 *   — pas de nouvelle variable d'env (parité .env.example #1487), rotation
 *   de clé liée au APP_KEY.
 * - **Normalisation** : email → lower/trim ; téléphone → strip des
 *   séparateurs, trim. La même normalisation DOIT être appliquée à
 *   l'écriture et à la recherche (seule garantie de retrouver le digest).
 * - **Masquage** : les logs, réponses d'erreur et exports ne contiennent
 *   jamais la PII en clair — `mask()` pour l'affichage partiel contrôlé.
 *
 * Le HMAC n'est PAS un mot de passe (pas de salage par valeur nécessaire :
 * un digest HMAC avec clé secrète ne se brute-force pas sans la clé). Il
 * s'ajoute au chiffrement AES-256 au repos (SensitiveDataEncryptor) : le
 * digest sert à la recherche, le chiffré sert à la lecture.
 */
class PiiHmacLookupService
{
    /** Salt de dérivation — ne pas changer (invaliderait tous les digests). */
    private const KEY_SALT = 'leopardo-crm-pii-hmac-v1';

    /** Longueur d'un digest hex HMAC-SHA256. */
    private const DIGEST_LENGTH = 64;

    public function hash(string $value): string
    {
        return hash_hmac('sha256', $this->normalize($value), $this->key());
    }

    /**
     * Alias de recherche : hache l'entrée pour une requête d'égalité exacte
     * sur la colonne `*_hmac`.
     */
    public function lookup(string $value): string
    {
        return $this->hash($value);
    }

    /**
     * Masque une PII pour les logs / interfaces (ex. « j***@e***.com »).
     * Ne JAMAIS utiliser pour stocker — uniquement pour afficher.
     */
    public function mask(string $value): string
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, '@')) {
            [$local, $domain] = explode('@', $normalized, 2);
            $domainParts = explode('.', $domain);

            return $this->maskFragment($local).'@'.$this->maskFragment($domainParts[0]).'.'.($domainParts[1] ?? '');
        }

        if (strlen($normalized) >= 6) {
            return substr($normalized, 0, 2).str_repeat('*', max(1, strlen($normalized) - 4)).substr($normalized, -2);
        }

        return str_repeat('*', strlen($normalized));
    }

    public function isHashed(string $value): bool
    {
        return strlen($value) === self::DIGEST_LENGTH && ctype_xdigit($value);
    }

    /**
     * Normalise une valeur PII avant hachage/masquage.
     */
    public function normalize(string $value): string
    {
        $normalized = strtolower(trim($value));

        // Téléphone : retirer les séparateurs courants (espaces, points,
        // tirets, parenthèses) et le préfixe '+' (forme E.164 sans '+' :
        // « +33612345678 » et « 33612345678 » sont le même numéro) —
        // cf. docs/security/CRM_PII_HMAC.md.
        if (! str_contains($normalized, '@')) {
            $normalized = preg_replace('/[\s.\-()]/', '', $normalized) ?? $normalized;
            $normalized = ltrim($normalized, '+');
        }

        return $normalized;
    }

    private function maskFragment(string $fragment): string
    {
        if ($fragment === '') {
            return '';
        }

        if (strlen($fragment) <= 2) {
            return $fragment[0].'*';
        }

        return $fragment[0].str_repeat('*', max(1, strlen($fragment) - 2));
    }

    private function key(): string
    {
        return hash_hmac('sha256', self::KEY_SALT, (string) config('app.key'));
    }
}
