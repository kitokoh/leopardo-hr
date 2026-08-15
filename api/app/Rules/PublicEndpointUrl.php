<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Anti-SSRF guard for user-supplied SSO endpoint URLs (SAML/OIDC).
 *
 * Issue #3318 : la règle `|url` acceptait 169.254.169.254, localhost et les
 * plages privées → le serveur faisait ensuite POST/GET vers ces cibles
 * (OidcFlowService::token, OidcIdTokenValidator::jwks).
 *
 * Rejects:
 *   - tout schéma autre que https://
 *   - les hôtes locaux : localhost, *.localhost, *.local, *.internal
 *   - les IP littérales privées/réservées (RFC1918, loopback, link-local,
 *     CGNAT, TEST-NET, IPv6 ULA/link-local, 169.254.169.254…)
 *   - les hostnames dont au moins un enregistrement DNS résout vers une IP
 *     privée/réservée
 *
 * Tolère (best-effort, défense en profondeur comme NotPrivateUrl) les
 * hostnames non résolubles au moment de la validation : on ne peut pas
 * prouver qu'ils sont privés, et le rejet casserait les configurations
 * d'IdP légitimes en cours de propagation DNS.
 */
class PublicEndpointUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('validation.url')->translate();

            return;
        }

        if (! str_starts_with(strtolower($value), 'https://')) {
            $fail('SSO URL must use https://.');

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $fail('SSO URL is invalid.');

            return;
        }

        if (! self::isPublicHost($host)) {
            $fail('SSO URL is not allowed (private, reserved, or local host).');
        }
    }

    public static function isPublicHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            return false;
        }

        // Literal IP: validate directly.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // Hostname: resolve and reject if *any* record lands on a
        // private/reserved range.
        $records = array_filter([
            ...(dns_get_record($host, DNS_A) ?: []),
            ...(dns_get_record($host, DNS_AAAA) ?: []),
        ]);

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! self::isPublicIp($ip)) {
                return false;
            }
        }

        // Unresolvable at validation time: tolerated (best-effort check).
        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
