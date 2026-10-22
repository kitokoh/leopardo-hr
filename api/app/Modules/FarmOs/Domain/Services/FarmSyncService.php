<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmConflictStrategy;
use App\Modules\FarmOs\Domain\Enums\FarmOfflineOperationType;
use App\Modules\FarmOs\Domain\Enums\FarmSyncStatus;
use App\Modules\FarmOs\Domain\Exceptions\FarmSyncException;
use App\Modules\FarmOs\Domain\Models\FarmSyncConflict;
use App\Modules\FarmOs\Domain\Models\FarmSyncOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T5.2 Moteur de synchronisation hors ligne.
 *
 * PRINCIPE DIRECTEUR : Farm OS ne promet JAMAIS une fonction hors ligne qu'il
 * n'implemente pas. Ce service applique exactement le registre
 * {@see FarmOfflineOperationType} et refuse tout le reste.
 *
 * GARANTIES :
 *   1. IDEMPOTENCE — `client_operation_id` est unique par entreprise. Le rejeu
 *      d'une operation deja appliquee retourne l'operation existante SANS rien
 *      re-appliquer. C'est ce qui empeche une mortalite ou une vente saisie deux
 *      fois hors reseau d'etre comptee deux fois.
 *   2. CONFLIT D'IDENTIFIANT — un meme identifiant pousse avec un contenu
 *      DIFFERENT n'est pas un rejeu : c'est un conflit, et il est conserve.
 *   3. AUCUN ECRASEMENT SILENCIEUX — les operations financieres et les
 *      mouvements de stock ne sont jamais tranchees automatiquement : elles
 *      partent en `manual_review` pour arbitrage humain.
 *   4. HISTORIQUE DES ERREURS — une operation refusee ou en echec reste visible
 *      avec son code d'erreur ; elle n'est jamais silencieusement perdue.
 *
 * Ce service ne fait AUCUN appel reseau et n'utilise AUCUNE facade Laravel
 * (regle de purete des Domain Services) : la connexion vient du modele.
 */
final class FarmSyncService
{
    /**
     * Nombre maximal de tentatives avant qu'une operation ne soit marquee en
     * echec definitif. Une operation en echec reste consultable.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Enregistre une operation poussee par un appareil.
     *
     * @param  array<string, mixed>  $payload
     * @return array{operation: FarmSyncOperation, replayed: bool, conflict: FarmSyncConflict|null}
     */
    public function submit(
        Company $company,
        string $clientOperationId,
        FarmOfflineOperationType $type,
        array $payload,
        Carbon $occurredAt,
        ?int $deviceId = null,
        ?int $submittedBy = null,
    ): array {
        if (trim($clientOperationId) === '') {
            throw FarmSyncException::missingClientOperationId();
        }

        $hash = $this->payloadHash($payload);

        $existing = $this->operations($company)
            ->where('client_operation_id', $clientOperationId)
            ->first();

        if ($existing instanceof FarmSyncOperation) {
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
                null,
                FarmConflictStrategy::ManualReview,
                $payload,
                $existing->payload,
                'FARM_SYNC_PAYLOAD_MISMATCH',
            );

            $existing->status = FarmSyncStatus::Conflict->value;
            $existing->last_error = FarmSyncException::payloadMismatch($clientOperationId)->errorCode();
            $existing->save();

            return [
                'operation' => $existing,
                'replayed' => false,
                'conflict' => $conflict,
            ];
        }

        $operation = new FarmSyncOperation([
            'client_operation_id' => $clientOperationId,
            'operation_type' => $type->value,
            'payload_hash' => $hash,
            'payload' => $payload,
            'status' => FarmSyncStatus::Pending->value,
            'attempt_count' => 0,
            'device_id' => $deviceId,
            'submitted_by' => $submittedBy,
            'occurred_at' => $occurredAt,
        ]);
        $operation->company_id = $company->id;
        $operation->save();

        return [
            'operation' => $operation,
            'replayed' => false,
            'conflict' => null,
        ];
    }

    /**
     * Marque une operation comme appliquee et memorise l'entite creee cote
     * serveur, ce qui permet a l'appareil de reconcilier son identifiant local.
     */
    public function markApplied(
        Company $company,
        FarmSyncOperation $operation,
        ?string $entityType = null,
        ?int $entityId = null,
    ): FarmSyncOperation {
        $this->assertOperationInCompany($company, $operation);

        $operation->status = FarmSyncStatus::Applied->value;
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
    public function markFailed(Company $company, FarmSyncOperation $operation, string $errorCode): FarmSyncOperation
    {
        $this->assertOperationInCompany($company, $operation);

        $operation->attempt_count = $operation->attempt_count + 1;
        $operation->last_error = $errorCode;
        $operation->status = $operation->attempt_count >= self::MAX_ATTEMPTS
            ? FarmSyncStatus::Failed->value
            : FarmSyncStatus::Pending->value;
        $operation->save();

        return $operation;
    }

    /**
     * Refuse une operation hors ligne non prevue par le registre.
     *
     * C'est la garde qui empeche Farm OS de promettre une fonction hors ligne
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
    ): FarmSyncOperation {
        $operation = new FarmSyncOperation([
            'client_operation_id' => $clientOperationId,
            'operation_type' => $operationType,
            'payload_hash' => $this->payloadHash($payload),
            'payload' => $payload,
            'status' => FarmSyncStatus::Rejected->value,
            'attempt_count' => 0,
            'last_error' => FarmSyncException::unsupportedOperation($operationType)->errorCode(),
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
     * arbitrage humain : Farm OS refuse de trancher a la place de l'exploitant
     * sur une donnee qui fausserait un stock ou une marge.
     */
    public function strategyFor(FarmOfflineOperationType $type): FarmConflictStrategy
    {
        if ($type->affectsStock() || $type->affectsFinance()) {
            return FarmConflictStrategy::ManualReview;
        }

        if ($type->isAdditive()) {
            return FarmConflictStrategy::LocalWins;
        }

        return FarmConflictStrategy::ServerWins;
    }

    /**
     * Resout un conflit. Un conflit deja resolu n'est jamais re-resolu : la
     * decision initiale reste auditable.
     */
    public function resolveConflict(
        Company $company,
        FarmSyncConflict $conflict,
        FarmConflictStrategy $resolution,
        ?int $resolvedBy = null,
        ?string $note = null,
    ): FarmSyncConflict {
        if ($conflict->company_id !== $company->id) {
            throw FarmSyncException::operationNotInCompany((int) $conflict->id);
        }

        if ($conflict->isResolved()) {
            throw FarmSyncException::conflictAlreadyResolved((int) $conflict->id);
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
     * @return list<FarmSyncOperation>
     */
    public function pending(Company $company, int $limit = 100): array
    {
        /** @var list<FarmSyncOperation> $rows */
        $rows = $this->operations($company)
            ->where('status', FarmSyncStatus::Pending->value)
            ->orderBy('occurred_at')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /**
     * HISTORIQUE DES ERREURS : operations refusees ou en echec, avec leur code.
     *
     * @return list<FarmSyncOperation>
     */
    public function errors(Company $company, int $limit = 100): array
    {
        /** @var list<FarmSyncOperation> $rows */
        $rows = $this->operations($company)
            ->whereIn('status', [FarmSyncStatus::Rejected->value, FarmSyncStatus::Failed->value])
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /**
     * Conflits en attente d'arbitrage humain.
     *
     * @return list<FarmSyncConflict>
     */
    public function unresolvedConflicts(Company $company, int $limit = 100): array
    {
        /** @var list<FarmSyncConflict> $rows */
        $rows = FarmSyncConflict::query()
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

        foreach (FarmSyncStatus::cases() as $status) {
            $counts[$status->value] = $this->operations($company)
                ->where('status', $status->value)
                ->count();
        }

        return [
            'pending' => $counts[FarmSyncStatus::Pending->value],
            'applied' => $counts[FarmSyncStatus::Applied->value],
            'conflict' => $counts[FarmSyncStatus::Conflict->value],
            'rejected' => $counts[FarmSyncStatus::Rejected->value],
            'failed' => $counts[FarmSyncStatus::Failed->value],
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
     * @return Builder<FarmSyncOperation>
     */
    private function operations(Company $company): Builder
    {
        return FarmSyncOperation::query()->where('company_id', $company->id);
    }

    private function assertOperationInCompany(Company $company, FarmSyncOperation $operation): void
    {
        if ($operation->company_id !== $company->id) {
            throw FarmSyncException::operationNotInCompany((int) $operation->id);
        }
    }

    /**
     * @param  array<string, mixed>  $localValue
     * @param  array<string, mixed>  $serverValue
     */
    private function recordConflict(
        Company $company,
        FarmSyncOperation $operation,
        string $entityType,
        ?int $entityId,
        FarmConflictStrategy $strategy,
        array $localValue,
        array $serverValue,
        ?string $note = null,
    ): FarmSyncConflict {
        $conflict = new FarmSyncConflict([
            'farm_sync_operation_id' => $operation->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'strategy' => $strategy->value,
            'local_value' => $localValue,
            'server_value' => $serverValue,
            'note' => $note,
        ]);
        $conflict->company_id = $company->id;
        $conflict->save();

        return $conflict;
    }
}
