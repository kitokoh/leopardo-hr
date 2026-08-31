<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

/**
 * MAT-009 (#5867) — redaction des PII et secrets dans les logs structures.
 *
 * Processeur Monolog branche sur le canal `structured` (et utilisable sur
 * tout canal JSON) : masque les valeurs portees par des cles sensibles
 * (password, token, secret, cle API, identifiants nationaux, IBAN, codes
 * 2FA...) ainsi que les motifs `cle=valeur` equivalents dans le message.
 *
 * Regles :
 * - masquage PAR CLE (recursif dans le contexte, y compris tableaux imbriques) ;
 * - valeurs `Bearer <token>` et `Basic <base64>` entierement masquees ;
 * - jamais de lecture ni de journalisation de la valeur d'origine ;
 * - les cles hors liste (request_id, duration_ms, method, uri...) passent
 *   inchangees pour preserver l'exploitabilite des logs.
 */
final class PiiRedactionProcessor
{
    public const REDACTED = '[REDACTED]';

    /**
     * Motifs de cles sensibles (comparaison insensible a la casse, sans
     * separateurs) : une cle qui CONTIENT l'un de ces motifs est masquee.
     */
    private const SENSITIVE_KEY_MARKERS = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'apikey',
        'api_key',
        'privatekey',
        'private_key',
        'client_secret',
        'access_key',
        'refresh_token',
        'nationalid',
        'national_id',
        'ssn',
        'iban',
        'recoverycode',
        'recovery_code',
        'otp',
        'totp',
        'twofa',
        'two_fa',
        'mfa',
    ];

    /** Motif de token porteur dans un message (Authorization: Bearer ...). */
    private const MESSAGE_KEY_VALUE_PATTERN = '/(?i)\b(password|passwd|secret|token|api[_-]?key|national[_-]?id|iban|authorization|client[_-]?secret|refresh[_-]?token)(\s*[=:]\s*)([^\s,&"\'<>]+)/';

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redactArray($record->context);
        $message = preg_replace(self::MESSAGE_KEY_VALUE_PATTERN, '$1$2'.self::REDACTED, $record->message) ?? $record->message;

        return new LogRecord(
            datetime: $record->datetime,
            channel: $record->channel,
            level: $record->level,
            message: $message,
            context: $context,
            extra: $record->extra,
        );
    }

    /**
     * @param  array<mixed, mixed>  $context
     * @return array<mixed, mixed>
     */
    private function redactArray(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redactArray($value);

                continue;
            }

            if (is_string($value)) {
                $redacted[$key] = $this->redactScalarString($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function redactScalarString(string $value): string
    {
        if (preg_match('/^\s*(Bearer|Basic)\s+\S+/i', $value) === 1) {
            return self::REDACTED;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $key));

        foreach (self::SENSITIVE_KEY_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }
}
