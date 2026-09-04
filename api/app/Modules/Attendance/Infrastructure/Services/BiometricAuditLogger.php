<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Infrastructure\Services;

use App\Modules\Attendance\Domain\Models\BiometricAuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Journal d'audit & observabilité biométrique (BIO-008, #6773).
 *
 * Règles de rédaction strictes :
 *   - l'API n'accepte QUE des ids, codes machine et une corrélation — aucune
 *     photo, aucun gabarit, aucun secret ne peut transiter ;
 *   - `context` est limité aux clés autorisées par la méthode (allowlist) ;
 *   - chaque événement est rattachable à un tenant (scope), un salarié, un
 *     site, un appareil (hash) et une corrélation.
 *
 * Observabilité : un événement structuré est aussi émis sur le canal
 * `biometric` (metrics/alertes — pics de rejets, appareil anormal, bascules),
 * avec les mêmes garanties de rédaction.
 */
final class BiometricAuditLogger
{
    /** @var list<string> clés autorisées dans le contexte structuré. */
    private const ALLOWED_CONTEXT_KEYS = [
        'version',
        'provider',
        'enrolled_via',
        'confidence_bucket',
        'latency_ms',
        'reason',
        'previous_status',
        'fallback_to',
        'config_key',
        'config_value',
        'threshold',
    ];

    /**
     * @param  array<string, mixed>  $context  uniquement des clés de l'allowlist
     */
    public function log(
        string $companyId,
        string $event,
        ?int $employeeId = null,
        ?int $kioskId = null,
        ?int $siteId = null,
        ?int $actorEmployeeId = null,
        ?string $method = null,
        ?string $resultCode = null,
        ?string $correlationId = null,
        ?string $deviceCodeHash = null,
        array $context = [],
    ): void {
        $context = $this->sanitizeContext($context, $event);

        BiometricAuditLog::query()->create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'kiosk_id' => $kioskId,
            'site_id' => $siteId,
            'actor_employee_id' => $actorEmployeeId,
            'event' => $event,
            'method' => $method,
            'result_code' => $resultCode,
            'correlation_id' => $correlationId,
            'device_code_hash' => $deviceCodeHash,
            'context' => $context,
        ]);

        // Observabilité : canal dédié, champs structurés, zéro contenu.
        Log::channel('biometric')->info('biometric.audit', array_merge($context, [
            'company_id' => $companyId,
            'event' => $event,
            'employee_id' => $employeeId,
            'kiosk_id' => $kioskId,
            'site_id' => $siteId,
            'method' => $method,
            'result_code' => $resultCode,
            'correlation_id' => $correlationId,
            'device_code_hash' => $deviceCodeHash,
        ]));
    }

    /**
     * Filtre le contexte sur l'allowlist (défense en profondeur : même un
     * appelant bugué ne peut pas écrire un payload biométrique dans l'audit).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context, string $event): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            if (in_array($key, self::ALLOWED_CONTEXT_KEYS, true)) {
                $sanitized[$key] = $value;
            }
        }

        // Garde anti-régression : si un appelant tente de journaliser un
        // contenu sensible, on le signale (jamais silencieusement ignoré).
        $rejected = array_diff(array_keys($context), self::ALLOWED_CONTEXT_KEYS);
        if ($rejected !== []) {
            Log::channel('biometric')->warning('biometric.audit.context_rejected', [
                'event' => $event,
                'rejected_keys' => array_values($rejected),
            ]);
        }

        return $sanitized;
    }
}
