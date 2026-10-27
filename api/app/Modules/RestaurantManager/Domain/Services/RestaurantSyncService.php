<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantConflictStrategy;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantOfflineOperationType;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantSyncStatus;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantSyncException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSyncConflict;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSyncOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Restaurant OS — REST-OS-7 Moteur de synchronisation hors ligne (#8609).
 *
 * PRINCIPE DIRECTEUR : le module ne promet JAMAIS une fonction hors ligne qu'il
 * n'implemente pas. Ce service applique exactement le registre
 * {@see RestaurantOfflineOperationType} et refuse tout le reste.
 *
 * GARANTIES :
 *   1. IDEMPOTENCE — `client_operation_id` est unique par entreprise. Le rejeu
 *      d'une operation deja recue retourne l'operation existante SANS rien
 *      re-appliquer. C'est ce qui empeche une commande ou un encaissement saisi
 *      deux fois hors reseau d'etre compte deux fois.
 *   2. CONFLIT D'IDENTIFIANT — un meme identifiant pousse avec un contenu
 *      DIFFERENT n'est pas un rejeu : c'est un conflit, et il est conserve.
 *   3. CONFLIT INTER-TERMINAUX — deux terminaux DECONNECTES ne peuvent pas
 *      confirmer silencieusement des operations INCOMPATIBLES sur la meme
 *      entite. Des qu'une operation vise une entite deja visee par un AUTRE
 *      terminal avec un contenu different, elle part en conflit au lieu
 *      d'ecraser la premiere. C'est le critere d'acceptation central de
 *      l'issue : sans cette regle, deux postes deconnectes cloturant la meme
 *      table produiraient deux verites, la derniere arrivant gagnant en
 *      silence.
 *   4. AUCUN ECRASEMENT SILENCIEUX — les encaissements et les mouvements de
 *      stock ne sont jamais tranches automatiquement : ils partent en
 *      `manual_review` pour arbitrage humain.
 *   5. HISTORIQUE DES ERREURS — une operation refusee ou en echec reste visible
 *      avec son code d'erreur ; elle n'est jamais silencieusement perdue.
 *
 * Ce service ne fait AUCUN appel reseau et n'utilise AUCUNE facade Laravel
 * (regle de purete des Domain Services) : la connexion vient du modele.
 */
final class RestaurantSyncService
{
    /**
     * Nombre maximal de tentatives avant qu'une operation ne soit marquee en
     * echec definitif. Une operation en echec reste consultable.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Enregistre une operation poussee par un terminal.
     *
     * @param  array<string, mixed>  $payload
     * @return array{operation: RestaurantSyncOperation, replayed: bool, conflict: RestaurantSyncConflict|null}
     */
    public function submit(
        Company $company,
        string $clientOperationId,
        RestaurantOfflineOperationType $type,
        array $payload,
        Carbon $occurredAt,
        ?string $terminalId = null,
        ?string $targetEntityType = null,
        ?string $targetEntityId = null,
        ?int $submittedBy = null,
    ): array {
        if (trim($clientOperationId) === '') {
            throw RestaurantSyncException::missingClientOperationId();
        }

        // Une operation qui VISE une entite doit la nommer : sans entite ciblee,
        // la detection de conflit inter-terminaux est impossible, donc on refuse
        // plutot que d'appliquer a l'aveugle (fail-closed).
        if ($type->targetsEntity() && ($targetEntityType === null || $targetEntityId === null)) {
            throw RestaurantSyncException::missingTargetEntity($type->value);
        }

        $hash = $this->payloadHash($payload);

        $existing = $this->operations($company)
            ->where('client_operation_id', $clientOperationId)
            ->first();

        if ($existing instanceof RestaurantSyncOperation) {
            // MEME identifiant + MEME contenu = rejeu : on ne re-applique rien.
            if ($existing->payload_hash === $hash) {
                return [
                    'operation' => $existing,
                    'replayed' => true,
                    'conflict' => null,
                ];
            }

            // MEME identifiant + contenu DIFFERENT = conflit d'identifiant.
            $conflict = $this->recordConflict(
                $company,
                $existing,
                $type->value,
                $targetEntityId,
                RestaurantConflictStrategy::ManualReview,
                $payload,
                $existing->payload,
                $terminalId,
                $existing->terminal_id,
                'RESTAURANT_SYNC_PAYLOAD_MISMATCH',
            );

            $existing->status = RestaurantSyncStatus::Conflict->value;
            $existing->last_error = RestaurantSyncException::payloadMismatch($clientOperationId)->errorCode();
            $existing->save();

            return [
                'operation' => $existing,
                'replayed' => false,
                'conflict' => $conflict,
            ];
        }

        $operation = new RestaurantSyncOperation([
            'client_operation_id' => $clientOperationId,
            'operation_type' => $type->value,
            'payload_hash' => $hash,
            'payload' => $payload,
            'status' => RestaurantSyncStatus::Pending->value,
            'attempt_count' => 0,
            'terminal_id' => $terminalId,
            'target_entity_type' => $targetEntityType,
            'target_entity_id' => $targetEntityId,
            'submitted_by' => $submittedBy,
            'occurred_at' => $occurredAt,
        ]);
        $operation->company_id = $company->id;
        $operation->save();

        // CONFLIT INTER-TERMINAUX : un AUTRE terminal a-t-il deja confirme une
        // operation differente sur la MEME entite ? Si oui, on ne laisse pas la
        // seconde ecraser la premiere en silence.
        $conflict = $this->detectInterTerminalConflict($company, $operation, $type);

        if ($conflict instanceof RestaurantSyncConflict) {
            $operation->status = RestaurantSyncStatus::Conflict->value;
            $operation->last_error = 'RESTAURANT_SYNC_INTER_TERMINAL_CONFLICT';
            $operation->save();
        }

        return [
            'operation' => $operation,
            'replayed' => false,
            'conflict' => $conflict,
        ];
    }

    /**
     * Marque une operation comme appliquee et memorise l'entite creee cote
     * serveur, ce qui permet au terminal de reconcilier son identifiant local.
     */
    public function markApplied(
        Company $company,
        RestaurantSyncOperation $operation,
        ?string $entityType = null,
        ?int $entityId = null,
    ): RestaurantSyncOperation {
        $this->assertOperationInCompany($company, $operation);

        $operation->status = RestaurantSyncStatus::Applied->value;
        $operation->applied_entity_type = $entityType;
        $operation->applied_entity_id = $entityId;
        $operation->applied_at = Carbon::now();
        $operation->last_error = null;
        $operation->save();

        return $operation;
    }

    /**
     * Marque une operation en echec. Elle reste VISIBLE dans l'historique des
     * erreurs ; au-dela de {@see self::MAX_ATTEMPTS} elle passe en echec
     * definitif plutot que d'etre rejouee indefiniment.
     */
    public function markFailed(Company $company, RestaurantSyncOperation $operation, string $errorCode): RestaurantSyncOperation
    {
        $this->assertOperationInCompany($company, $operation);

        $operation->attempt_count = $operation->attempt_count + 1;
        $operation->last_error = $errorCode;
        $operation->status = $operation->attempt_count >= self::MAX_ATTEMPTS
            ? RestaurantSyncStatus::Failed->value
            : RestaurantSyncStatus::Pending->value;
        $operation->save();

        return $operation;
    }

    /**
     * Refuse une operation hors ligne non prevue par le registre.
     *
     * C'est la garde qui empeche le module de promettre une fonction hors ligne
     * qu'il n'implemente pas : l'operation est enregistree en `rejected` avec un
     * code d'erreur stable, jamais appliquee « au mieux ».
     *
     * @param  array<string, mixed>  $payload
     */
    public function rejectUnsupported(
        Company $company,
        string $clientOperationId,
        string $operationType,
        array $payload,
        Carbon $occurredAt,
        ?string $terminalId = null,
    ): RestaurantSyncOperation {
        $operation = new RestaurantSyncOperation([
            'client_operation_id' => $clientOperationId,
            'operation_type' => $operationType,
            'payload_hash' => $this->payloadHash($payload),
            'payload' => $payload,
            'status' => RestaurantSyncStatus::Rejected->value,
            'attempt_count' => 0,
            'last_error' => RestaurantSyncException::unsupportedOperation($operationType)->errorCode(),
            'terminal_id' => $terminalId,
            'occurred_at' => $occurredAt,
        ]);
        $operation->company_id = $company->id;
        $operation->save();

        return $operation;
    }

    /**
     * Strategie de resolution applicable a une operation.
     *
     * Les operations ADDITIVES peuvent etre appliquees localement sans risque
     * d'ecrasement. Les operations touchant le STOCK ou les FINANCES exigent un
     * arbitrage humain : le systeme refuse de trancher a la place du
     * restaurateur sur une donnee qui fausserait une recette ou un stock.
     */
    public function strategyFor(RestaurantOfflineOperationType $type): RestaurantConflictStrategy
    {
        if ($type->affectsStock() || $type->affectsFinance()) {
            return RestaurantConflictStrategy::ManualReview;
        }

        if ($type->isAdditive()) {
            return RestaurantConflictStrategy::LocalWins;
        }

        return RestaurantConflictStrategy::ServerWins;
    }

    /**
     * Resout un conflit. Un conflit deja resolu n'est jamais re-resolu : la
     * decision initiale reste auditable.
     */
    public function resolveConflict(
        Company $company,
        RestaurantSyncConflict $conflict,
        RestaurantConflictStrategy $resolution,
        ?int $resolvedBy = null,
        ?string $note = null,
    ): RestaurantSyncConflict {
        if ($conflict->company_id !== $company->id) {
            throw RestaurantSyncException::operationNotInCompany((int) $conflict->id);
        }

        if ($conflict->isResolved()) {
            throw RestaurantSyncException::conflictAlreadyResolved((int) $conflict->id);
        }

        $conflict->resolution = $resolution->value;
        $conflict->resolved_by = $resolvedBy;
        $conflict->resolved_at = Carbon::now();
        $conflict->note = $note;
        $conflict->save();

        return $conflict;
    }

    /**
     * Operations en attente de synchronisation, les plus anciennes d'abord.
     *
     * @return list<RestaurantSyncOperation>
     */
    public function pending(Company $company, int $limit = 100): array
    {
        /** @var list<RestaurantSyncOperation> $rows */
        $rows = $this->operations($company)
            ->where('status', RestaurantSyncStatus::Pending->value)
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /**
     * HISTORIQUE DES ERREURS : operations refusees ou en echec, avec leur code.
     *
     * @return list<RestaurantSyncOperation>
     */
    public function errors(Company $company, int $limit = 100): array
    {
        /** @var list<RestaurantSyncOperation> $rows */
        $rows = $this->operations($company)
            ->whereIn('status', [RestaurantSyncStatus::Rejected->value, RestaurantSyncStatus::Failed->value])
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /**
     * Conflits en attente d'arbitrage humain.
     *
     * @return list<RestaurantSyncConflict>
     */
    public function unresolvedConflicts(Company $company, int $limit = 100): array
    {
        /** @var list<RestaurantSyncConflict> $rows */
        $rows = RestaurantSyncConflict::query()
            ->where('company_id', $company->id)
            ->whereNull('resolution')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /**
     * Etat de la file de synchronisation, tel qu'affiche sur le tableau de bord.
     *
     * @return array{pending:int, applied:int, conflict:int, rejected:int, failed:int, unresolved_conflicts:int}
     */
    public function statusSummary(Company $company): array
    {
        $counts = [];

        foreach (RestaurantSyncStatus::cases() as $status) {
            $counts[$status->value] = $this->operations($company)
                ->where('status', $status->value)
                ->count();
        }

        return [
            'pending' => $counts[RestaurantSyncStatus::Pending->value],
            'applied' => $counts[RestaurantSyncStatus::Applied->value],
            'conflict' => $counts[RestaurantSyncStatus::Conflict->value],
            'rejected' => $counts[RestaurantSyncStatus::Rejected->value],
            'failed' => $counts[RestaurantSyncStatus::Failed->value],
            'unresolved_conflicts' => count($this->unresolvedConflicts($company)),
        ];
    }

    /**
     * Empreinte stable du contenu d'une operation : sert a distinguer un rejeu
     * (meme contenu) d'un conflit d'identifiant (contenu different).
     *
     * @param  array<string, mixed>  $payload
     */
    public function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * @return Builder<RestaurantSyncOperation>
     */
    private function operations(Company $company): Builder
    {
        return RestaurantSyncOperation::query()->where('company_id', $company->id);
    }

    private function assertOperationInCompany(Company $company, RestaurantSyncOperation $operation): void
    {
        if ($operation->company_id !== $company->id) {
            throw RestaurantSyncException::operationNotInCompany((int) $operation->id);
        }
    }

    /**
     * Detecte qu'un AUTRE terminal a deja confirme une operation differente sur
     * la MEME entite cible.
     *
     * C'est le critere d'acceptation « deux terminaux deconnectes ne peuvent pas
     * confirmer silencieusement des operations incompatibles ». La comparaison
     * porte sur `payload_hash` : deux terminaux qui confirment EXACTEMENT la
     * meme chose (meme contenu) ne sont pas en conflit — c'est une convergence,
     * pas une divergence. Seul un contenu DIFFERENT sur la meme entite est un
     * conflit.
     *
     * La strategie retenue est celle de la nature de l'operation : un
     * encaissement ou un mouvement de stock part en arbitrage humain, une
     * operation additive est tranchee localement.
     */
    private function detectInterTerminalConflict(
        Company $company,
        RestaurantSyncOperation $operation,
        RestaurantOfflineOperationType $type,
    ): ?RestaurantSyncConflict {
        if (! $type->targetsEntity()) {
            return null;
        }

        if ($operation->target_entity_type === null || $operation->target_entity_id === null) {
            return null;
        }

        $rival = $this->operations($company)
            ->where('target_entity_type', $operation->target_entity_type)
            ->where('target_entity_id', $operation->target_entity_id)
            ->where('id', '!=', $operation->id)
            ->whereIn('status', [
                RestaurantSyncStatus::Pending->value,
                RestaurantSyncStatus::Applied->value,
                RestaurantSyncStatus::Conflict->value,
            ])
            ->where('payload_hash', '!=', $operation->payload_hash)
            ->orderBy('occurred_at')
            ->first();

        if (! $rival instanceof RestaurantSyncOperation) {
            return null;
        }

        // Meme terminal : c'est une correction du meme poste, pas un conflit
        // inter-terminaux. Le terminal sait ce qu'il a fait ; on ne bloque pas
        // sa propre sequence.
        if ($operation->terminal_id !== null && $operation->terminal_id === $rival->terminal_id) {
            return null;
        }

        return $this->recordConflict(
            $company,
            $operation,
            $operation->target_entity_type,
            $operation->target_entity_id,
            $this->strategyFor($type),
            $operation->payload,
            $rival->payload,
            $operation->terminal_id,
            $rival->terminal_id,
            'RESTAURANT_SYNC_INTER_TERMINAL_CONFLICT',
        );
    }

    /**
     * @param  array<string, mixed>  $localValue
     * @param  array<string, mixed>  $serverValue
     */
    private function recordConflict(
        Company $company,
        RestaurantSyncOperation $operation,
        string $entityType,
        ?string $entityId,
        RestaurantConflictStrategy $strategy,
        array $localValue,
        array $serverValue,
        ?string $localTerminalId,
        ?string $serverTerminalId,
        ?string $note = null,
    ): RestaurantSyncConflict {
        $conflict = new RestaurantSyncConflict([
            'restaurant_sync_operation_id' => $operation->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'strategy' => $strategy->value,
            'local_value' => $localValue,
            'server_value' => $serverValue,
            'local_terminal_id' => $localTerminalId,
            'server_terminal_id' => $serverTerminalId,
            'note' => $note,
        ]);
        $conflict->company_id = $company->id;
        $conflict->save();

        return $conflict;
    }
}
