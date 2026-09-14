<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

use App\Modules\Platform\Domain\Models\PlatformOutboxEvent;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #5866 — Publication d'événements dans l'outbox plateforme (MAT-008).
 *
 * À appeler au moment de l'événement métier (listener synchrone) : l'effet
 * est d'abord persisté, puis consommé de façon asynchrone et idempotente.
 * Idempotence : clé dérivée du (event_type, payload) par défaut, ou fournie ;
 * la contrainte unique (company_id, idempotency_key) déduplique les rejets.
 *
 * DEUX GARANTIES DE SÛRETÉ (constatées nécessaires le 2026-09-14) :
 *
 * 1. **La publication ne peut plus empoisonner la transaction de l'appelant.**
 *    Toute l'opération vit dans un SAVEPOINT (`DB::transaction` imbriquée) :
 *    une erreur SQL PostgreSQL avorte la transaction COURANTE, et un `catch`
 *    PHP ne la rattrape pas — l'appelant enchaînait alors des `25P02
 *    current transaction is aborted` (observé : création de société du
 *    parcours d'essai self-service, et suite de tests Feature **bloquée**,
 *    la connexion avortée conservant ses verrous).
 * 2. **La table est résolue explicitement** (`resolveTableSchema`, garde F-17)
 *    au lieu de dépendre du `search_path` de la session : `VerifyTrialSignup`
 *    positionne `search_path TO public` avant de provisionner, et la table
 *    vit dans `shared_tenants` — le pre-SELECT échouait donc à chaque
 *    création de société sur ce parcours (`platform.outbox.publish_failed.
 *    company_created` dans les logs), perdant silencieusement l'événement.
 */
final class PlatformOutboxPublisher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function publish(
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): PlatformOutboxEvent {
        $key = $idempotencyKey ?? hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));

        $table = $this->qualifiedTable();

        try {
            // Savepoint englobant : même en cas d'échec SQL (table absente,
            // colonne manquante, contrainte), la transaction de l'appelant
            // reste utilisable.
            return DB::transaction(function () use (
                $table, $companyId, $key, $eventType, $payload, $aggregateType, $aggregateId, $availableAt
            ): PlatformOutboxEvent {
                // Dédup : pre-SELECT (cas nominal) PUIS INSERT en transaction
                // imbriquée (savepoint). Une violation unique PostgreSQL ABORTE
                // la transaction courante (25P02) : sans savepoint, le SELECT du
                // catch échoue en cascade (même garde que l'outbox CRM #5741).
                $existing = (new PlatformOutboxEvent)->setTable($table)->newQuery()
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $key)
                    ->first();

                if ($existing instanceof PlatformOutboxEvent) {
                    return $existing;
                }

                try {
                    return DB::transaction(function () use (
                        $table, $companyId, $key, $eventType, $payload, $aggregateType, $aggregateId, $availableAt
                    ): PlatformOutboxEvent {
                        /** @var PlatformOutboxEvent $event */
                        $event = (new PlatformOutboxEvent)->setTable($table);

                        $event->forceFill([
                            'company_id' => $companyId,
                            'event_type' => $eventType,
                            'aggregate_type' => $aggregateType,
                            'aggregate_id' => $aggregateId,
                            'payload' => $payload,
                            'status' => PlatformOutboxEvent::STATUS_PENDING,
                            'idempotency_key' => $key,
                            'available_at' => $availableAt ?? now(),
                        ])->save();

                        return $event;
                    });
                } catch (UniqueConstraintViolationException) {
                    /** @var PlatformOutboxEvent $existing */
                    $existing = (new PlatformOutboxEvent)->setTable($table)->newQuery()
                        ->where('company_id', $companyId)
                        ->where('idempotency_key', $key)
                        ->firstOrFail();

                    return $existing;
                }
            });
        } catch (Throwable $exception) {
            // Le savepoint a déjà été annulé : la transaction de l'appelant est
            // intacte. On remonte l'erreur telle quelle — le listener appelant
            // l'absorbe et la journalise (effet de bord auxiliaire : il ne doit
            // jamais faire échouer l'opération métier, #6958).
            throw $exception;
        }
    }

    /**
     * Nom de table qualifié par le schéma réel (garde F-17), pour ne pas
     * dépendre du `search_path` de la session.
     */
    private function qualifiedTable(): string
    {
        // `resolveTableSchema()` (garde F-17) filtre sur `current_schemas()` :
        // quand l'appelant a positionné `SET search_path TO public` (parcours
        // d'essai self-service), la table — qui vit dans `shared_tenants` —
        // n'est PAS dans le search_path et la résolution renvoie null. On
        // cherche donc explicitement dans les schémas canoniques, en
        // privilégiant `shared_tenants`.
        $schema = resolveTableSchema('platform_outbox_events');

        if ($schema === null) {
            $row = DB::selectOne(
                "SELECT table_schema FROM information_schema.tables
                  WHERE table_name = 'platform_outbox_events'
                    AND table_schema IN ('shared_tenants', 'public')
                  ORDER BY CASE table_schema WHEN 'shared_tenants' THEN 0 ELSE 1 END
                  LIMIT 1"
            );

            $schema = $row !== null ? (string) $row->table_schema : null;
        }

        return $schema !== null ? $schema.'.platform_outbox_events' : 'platform_outbox_events';
    }
}
