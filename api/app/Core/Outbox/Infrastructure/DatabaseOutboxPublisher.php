<?php

declare(strict_types=1);

namespace App\Core\Outbox\Infrastructure;

use App\Core\Outbox\Contracts\OutboxPublisher;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * #8209 — Implementation unique de l'outbox transactionnel.
 *
 * Extraite de `PlatformOutboxPublisher`, qui etait la seule des six copies a
 * porter les deux garanties de surete decouvertes en production le
 * 2026-09-14. Les cinq autres en etaient depourvues :
 *
 * 1. **La publication ne peut pas empoisonner la transaction appelante.**
 *    Toute l'operation vit dans un SAVEPOINT (`DB::transaction` imbriquee) :
 *    une erreur SQL PostgreSQL avorte la transaction COURANTE, et un `catch`
 *    PHP ne la rattrape pas — l'appelant enchainait alors des `25P02
 *    current transaction is aborted`, avec la connexion avortee conservant
 *    ses verrous (suite Feature bloquee observee sur la creation de societe
 *    du parcours d'essai self-service).
 * 2. **La table est resolue explicitement** (`OutboxTableResolver`, garde
 *    F-17) au lieu de dependre du `search_path` : `VerifyTrialSignup`
 *    positionne `SET search_path TO public` avant de provisionner, et la
 *    table vit dans `shared_tenants` — le pre-SELECT echouait donc a chaque
 *    creation de societe, perdant silencieusement l'evenement.
 *
 * Ce que le core NE fait pas : decider du sort de l'echec. Il remonte
 * l'exception brute ; absorber reste un choix du module (un effet de bord
 * auxiliaire ne doit jamais faire echouer l'operation metier, #6958).
 *
 * HETEROGENEITE ASSUMEE DES SCHEMAS : les six outbox ne sont pas identiques.
 * `TravelAgency` nomme sa colonne `payload_redacted` (minimisation PII
 * documentee) et ne porte ni `aggregate_type` ni `aggregate_id`. Le noyau
 * inspecte donc les colonnes du modele avant d'ecrire, plutot que d'imposer
 * un schema unique qui detruirait la garantie de minimisation ou casserait
 * les verticales existantes.
 */
final class DatabaseOutboxPublisher implements OutboxPublisher
{
    /** @var array<class-string, list<string>> */
    private array $columnCache = [];

    public function __construct(
        private readonly OutboxTableResolver $resolver = new OutboxTableResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  class-string  $modelClass
     */
    public function publish(
        string $modelClass,
        string $companyId,
        string $eventType,
        array $payload,
        ?string $idempotencyKey = null,
        ?string $aggregateType = null,
        ?string $aggregateId = null,
        ?DateTimeInterface $availableAt = null,
    ): object {
        /** @var Model $probe */
        $probe = new $modelClass;
        $qualified = $this->resolver->qualified($probe->getTable());
        $key = $idempotencyKey ?? $this->deriveKey($eventType, $payload);

        // Savepoint englobant : meme en cas d'echec SQL (table absente,
        // colonne manquante, contrainte), la transaction de l'appelant reste
        // utilisable.
        return DB::transaction(fn (): object => $this->insideSavepoint(
            $qualified,
            $modelClass,
            $companyId,
            $eventType,
            $payload,
            $key,
            $aggregateType,
            $aggregateId,
            $availableAt,
        ));
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $payload
     */
    private function insideSavepoint(
        string $qualified,
        string $modelClass,
        string $companyId,
        string $eventType,
        array $payload,
        string $key,
        ?string $aggregateType,
        ?string $aggregateId,
        ?DateTimeInterface $availableAt,
    ): object {
        $columns = $this->columnsOf($modelClass);

        $attributes = [
            'company_id' => $companyId,
            'event_type' => $eventType,
            'status' => 'pending',
            'idempotency_key' => $key,
            'available_at' => $availableAt ?? now(),
        ];

        // Les agregats ne sont pas portes par toutes les outbox (Travel n'en a
        // pas) : on n'ecrit que ce que le modele declare.
        if (in_array('aggregate_type', $columns, true)) {
            $attributes['aggregate_type'] = $aggregateType;
        }

        if (in_array('aggregate_id', $columns, true)) {
            $attributes['aggregate_id'] = $aggregateId;
        }

        $payloadColumn = in_array('payload', $columns, true)
            ? 'payload'
            : (in_array('payload_redacted', $columns, true) ? 'payload_redacted' : null);

        if ($payloadColumn === null) {
            throw new RuntimeException(sprintf(
                'Outbox %s : aucune colonne payload (`payload` ou `payload_redacted`) — modele non conforme au contrat.',
                $qualified,
            ));
        }

        $attributes[$payloadColumn] = $payload;

        // Dedoublonnage : pre-SELECT (cas nominal) PUIS INSERT en savepoint.
        // Une violation unique PostgreSQL ABORTE la transaction courante
        // (25P02) : sans savepoint, le SELECT du catch echouerait en cascade.
        $existing = $this->query($modelClass, $qualified)
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($qualified, $modelClass, $attributes): object {
                /** @var Model $event */
                $event = new $modelClass;
                $event->setTable($qualified);
                $event->forceFill($attributes)->save();

                return $event;
            });
        } catch (UniqueConstraintViolationException) {
            // Course entre deux publications concurrentes : la ligne gagnante
            // existe deja, on la retourne (comportement attendu d'un outbox).
            $winner = $this->query($modelClass, $qualified)
                ->where('company_id', $companyId)
                ->where('idempotency_key', $key)
                ->first();

            if ($winner instanceof Model) {
                return $winner;
            }

            throw new RuntimeException(sprintf(
                'Outbox : violation de contrainte inattendue sur %s (cle %s) sans ligne existante.',
                $qualified,
                $key,
            ));
        }
    }

    /**
     * Colonnes reellement ecrivables du modele (issues de `$fillable`).
     *
     * On lit `$fillable` et non le schema physique : ce sont les colonnes que
     * le modele accepte, donc celles dont l'ecriture sera persistee.
     *
     * @param  class-string  $modelClass
     * @return list<string>
     */
    private function columnsOf(string $modelClass): array
    {
        if (isset($this->columnCache[$modelClass])) {
            return $this->columnCache[$modelClass];
        }

        /** @var Model $probe */
        $probe = new $modelClass;

        /** @var list<string> $columns */
        $columns = $probe->getFillable();

        return $this->columnCache[$modelClass] = $columns;
    }

    /**
     * @param  class-string  $modelClass
     * @return Builder<Model>
     */
    private function query(string $modelClass, string $qualified): Builder
    {
        /** @var Model $probe */
        $probe = new $modelClass;
        $probe->setTable($qualified);

        return $probe->newQuery();
    }

    /**
     * Cle d'idempotence derivee du contenu : deux publications du meme
     * evenement avec le meme payload ne creent qu'une ligne.
     *
     * @param  array<string, mixed>  $payload
     */
    private function deriveKey(string $eventType, array $payload): string
    {
        return hash('sha256', $eventType.'|'.json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
