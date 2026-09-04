<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Consumers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\FuelStation\Domain\Contracts\FuelOutboxConsumer;
use App\Modules\FuelStation\Domain\Exceptions\PermanentFuelOutboxException;
use Illuminate\Support\Carbon;

/**
 * Consommateur du contrat Accounting (FUEL-015, issue #5809).
 *
 * Reçoit les agrégats VALIDÉS publiés par FuelStation (`fuel.cash.closed.v1`,
 * `fuel.shift.closed.v1`) et matérialise une trace d'audit de synthèse dans
 * le module propriétaire. Les écritures comptables elles-mêmes restent du
 * ressort du module Accounting (consommateur dédié enregistré dans son
 * provider, sans import croisé) ; ce consommateur garantit ici :
 *
 * - idempotence (rejeu du même événement → une seule trace, via la clé
 *   d'idempotence portée par le payload) ;
 * - versionnage (`schema_version` vérifié — événement inconnu → dead-letter) ;
 * - aucun accès direct aux tables FuelStation depuis Accounting : l'agrégat
 *   est LA source de vérité du contrat.
 */
final class FuelAccountingContractConsumer implements FuelOutboxConsumer
{
    /** @var list<string> */
    private const SUPPORTED_EVENTS = ['fuel.cash.closed.v1', 'fuel.shift.closed.v1'];

    private const SUPPORTED_VERSIONS = ['1.0'];

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED_EVENTS, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $version = is_string($payload['schema_version'] ?? null) ? $payload['schema_version'] : null;

        if (! in_array($version, self::SUPPORTED_VERSIONS, true)) {
            throw new PermanentFuelOutboxException(
                'Version de schéma non supportée: '.($version ?? 'null')
            );
        }

        $companyId = is_string($payload['company_id'] ?? null) ? $payload['company_id'] : null;
        if ($companyId === null) {
            throw new PermanentFuelOutboxException(
                'Payload sans company_id'
            );
        }

        $idempotencyKey = is_string($payload['idempotency_key'] ?? null) ? $payload['idempotency_key'] : null;
        if ($idempotencyKey === null) {
            throw new PermanentFuelOutboxException(
                'Payload sans idempotency_key'
            );
        }

        $eventName = is_string($payload['event'] ?? null) ? $payload['event'] : 'aggregate';
        $action = 'fuel.accounting.'.$eventName;

        $already = AuditLog::query()
            ->where('company_id', $companyId)
            ->where('action', $action)
            ->where('metadata->idempotency_key', $idempotencyKey)
            ->exists();

        if ($already) {
            return; // Idempotence : rejeu sans effet dupliqué.
        }

        AuditLog::record(
            module: 'fuel',
            action: $action,
            actor: null,
            newValues: [
                'event' => $eventName,
                'schema_version' => $version,
                'aggregate' => is_array($payload['aggregate'] ?? null) ? $payload['aggregate'] : [],
                'generated_at' => Carbon::now('UTC')->toIso8601String(),
            ],
            metadata: [
                'idempotency_key' => $idempotencyKey,
            ],
        );
    }
}
