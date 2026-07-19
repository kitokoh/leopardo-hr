<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Anti-SSRF guard for user-supplied outbound webhook URLs.
 *
 * Rejects URLs that are not https://, or whose host resolves (at validation
 * time) to a private/reserved/loopback IP range (RFC 1918, loopback,
 * link-local incl. the 169.254.169.254 cloud metadata address, etc.).
 *
 * This is a best-effort, defence-in-depth check: DNS can still be rebound
 * between validation and delivery, so the delivery job (DispatchWebhook)
 * re-resolves and re-checks the host immediately before making the request.
 *
 * See docs/security/AUDIT_API_2026-07-19.md, section 2.
 */
class NotPrivateUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.url')->translate();

            return;
        }

        if (! str_starts_with($value, 'https://')) {
            $fail('Webhook URL must use https://.');

            return;
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            $fail('Webhook URL is invalid.');

            return;
        }

        if (! self::isPublicHost($host)) {
            $fail('Webhook URL is not allowed (private, reserved, or unresolvable host).');
        }
    }

    public static function isPublicHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        // Literal IP: validate directly.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // Hostname: resolve to every A/AAAA record and reject if *any* of
        // them lands in a private/reserved range (defends against DNS
        // records mixing a public and a private/loopback answer).
        $records = array_filter([
            ...(dns_get_record($host, DNS_A) ?: []),
            ...(dns_get_record($host, DNS_AAAA) ?: []),
        ]);

        if ($records === []) {
            // Could not resolve at all: fail closed.
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (! is_string($ip) || ! self::isPublicIp($ip)) {
                return false;
            }
        }

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
