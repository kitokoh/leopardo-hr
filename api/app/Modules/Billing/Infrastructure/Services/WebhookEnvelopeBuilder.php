<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Services;

use Illuminate\Support\Str;

/**
 * Construit l'enveloppe canonique des webhooks sortants Leopardo et la
 * signature HMAC associée (schéma Svix-compatible).
 *
 * Issue #5744 (CRM PRE) — versionner les contrats API et événements :
 * l'enveloppe est ADDITIVE et rétro-compatible. Les champs hérités
 * (`timestamp`, `data`, en-têtes `X-Leopardo-*`) restent émis tels quels ;
 * les nouveaux champs (`event_version`, `company_id`, `correlation_id`,
 * `occurred_at`) et l'en-tête `X-Leopardo-Event-Version` sont ajoutés sans
 * rupture pour les consommateurs existants.
 *
 * Contrat documenté dans docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md
 * (§ Webhooks) et docs/api/VERSIONING.md (§ 5. Événements sortants).
 */
final class WebhookEnvelopeBuilder
{
    /**
     * Version courante de l'enveloppe d'événement.
     *
     * Incrémentée UNIQUEMENT pour un changement incompatible du contrat
     * d'événement (suppression/renommage de champ) — la procédure de
     * dépréciation est décrite dans docs/api/VERSIONING.md § 5.
     */
    public const CURRENT_VERSION = 1;

    /**
     * Construit l'enveloppe canonique du payload webhook sortant.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     event: string,
     *     event_version: int,
     *     company_id: ?string,
     *     correlation_id: string,
     *     occurred_at: string,
     *     timestamp: string,
     *     data: array<string, mixed>
     * }
     */
    public static function build(
        string $event,
        int $eventVersion,
        ?string $companyId,
        string $correlationId,
        string $occurredAt,
        array $data,
    ): array {
        return [
            'event' => $event,
            'event_version' => $eventVersion,
            'company_id' => $companyId,
            'correlation_id' => $correlationId,
            'occurred_at' => $occurredAt,
            // Champs hérités — rétro-compatibilité (docs guide partenaire).
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];
    }

    /**
     * Signature HMAC-SHA256 du payload brut signé (Svix-compatible).
     *
     * Format : sha256($timestamp . "." . $rawBody) avec le secret de
     * l'endpoint — même schéma que les vérifications côté partenaire
     * documentées dans docs/security/WEBHOOKS.md.
     */
    public static function sign(string $secret, string $rawJsonBody, int $timestamp): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$rawJsonBody}", $secret);
    }

    /**
     * En-têtes canoniques d'une livraison webhook sortante.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, string>
     */
    public static function headers(string $event, string $eventVersion, string $secret, array $body, int $timestamp): array
    {
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        $signature = self::sign($secret, $jsonBody, $timestamp);

        return [
            'Webhook-Id' => Str::uuid()->toString(),
            'Webhook-Timestamp' => (string) $timestamp,
            'Webhook-Signature' => "v1={$signature},t={$timestamp}",
            'X-Leopardo-Event' => $event, // Legacy — conservé
            'X-Leopardo-Signature' => $signature, // Legacy — conservé
            'X-Leopardo-Event-Version' => $eventVersion,
            'Content-Type' => 'application/json',
        ];
    }
}
