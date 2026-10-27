<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantConflictStrategy;
use App\Modules\RestaurantManager\Domain\Enums\RestaurantOfflineOperationType;
use App\Modules\RestaurantManager\Domain\Exceptions\RestaurantSyncException;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSyncConflict;
use App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions;
use App\Modules\RestaurantManager\Domain\Services\RestaurantSyncService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\RestaurantSyncSubmitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Restaurant OS — REST-OS-7 Surface de synchronisation hors ligne (#8609).
 *
 * Toutes les routes sont protegees par la permission `restaurant.server`
 * (operation de salle) : un terminal de caisse ou de service est exactement le
 * persona qui saisit hors reseau. La lecture des conflits exige en plus la
 * capacite de rapport, car arbitrer un conflit est un geste de pilotage.
 */
final class RestaurantSyncController
{
    public function __construct(
        private readonly RestaurantSyncService $sync,
    ) {}

    /**
     * Pousse un lot d'operations saisies hors reseau.
     *
     * Le resultat est renvoye PAR operation : le terminal ne marque `synced` que
     * ce qui a ete reellement accepte, et conserve le reste pour rejeu.
     */
    public function submitOperations(RestaurantSyncSubmitRequest $request): JsonResponse
    {
        $this->authorizeOperate($request);

        $company = $this->company($request);
        $results = [];

        /** @var list<array<string, mixed>> $operations */
        $operations = $request->validated('operations');

        foreach ($operations as $entry) {
            $clientOperationId = (string) ($entry['client_operation_id'] ?? '');
            $operationType = (string) ($entry['operation_type'] ?? '');
            /** @var array<string, mixed> $payload */
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
            $occurredAt = Carbon::parse((string) ($entry['occurred_at'] ?? Carbon::now()->toIso8601String()));
            $terminalId = isset($entry['terminal_id']) ? (string) $entry['terminal_id'] : null;
            $targetEntityType = isset($entry['target_entity_type']) ? (string) $entry['target_entity_type'] : null;
            $targetEntityId = isset($entry['target_entity_id']) ? (string) $entry['target_entity_id'] : null;

            $type = RestaurantOfflineOperationType::tryFrom($operationType);

            // Registre FERME : une operation hors ligne non prevue est REFUSEE et
            // conservee, jamais appliquee « au mieux ».
            if ($type === null) {
                $rejected = $this->sync->rejectUnsupported(
                    $company,
                    $clientOperationId,
                    $operationType,
                    $payload,
                    $occurredAt,
                    $terminalId,
                );

                $results[] = [
                    'client_operation_id' => $clientOperationId,
                    'status' => $rejected->status,
                    'error_code' => $rejected->last_error,
                ];

                continue;
            }

            try {
                $outcome = $this->sync->submit(
                    $company,
                    $clientOperationId,
                    $type,
                    $payload,
                    $occurredAt,
                    $terminalId,
                    $targetEntityType,
                    $targetEntityId,
                    $this->actorId($request),
                );
            } catch (RestaurantSyncException $exception) {
                // Forme invalide (entite ciblee manquante, identifiant vide) :
                // l'operation est refusee avec un code stable, jamais appliquee.
                $results[] = [
                    'client_operation_id' => $clientOperationId,
                    'status' => 'rejected',
                    'error_code' => $exception->errorCode(),
                ];

                continue;
            }

            $results[] = [
                'client_operation_id' => $clientOperationId,
                'status' => $outcome['operation']->status,
                'replayed' => $outcome['replayed'],
                'conflict_id' => $outcome['conflict'] instanceof RestaurantSyncConflict
                    ? (int) $outcome['conflict']->id
                    : null,
            ];
        }

        return response()->json(['data' => $results]);
    }

    /**
     * Etat de la file : ce qui reste a synchroniser, ce qui est en conflit.
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $this->authorizeOperate($request);

        return response()->json([
            'data' => $this->sync->statusSummary($this->company($request)),
        ]);
    }

    /**
     * HISTORIQUE DES ERREURS : operations refusees ou en echec.
     */
    public function syncErrors(Request $request): JsonResponse
    {
        $this->authorizeOperate($request);

        $company = $this->company($request);

        return response()->json([
            'data' => array_map(
                static fn ($operation): array => [
                    'id' => (int) $operation->id,
                    'client_operation_id' => (string) $operation->client_operation_id,
                    'operation_type' => (string) $operation->operation_type,
                    'status' => (string) $operation->status,
                    'attempt_count' => (int) $operation->attempt_count,
                    'error_code' => $operation->last_error,
                    'terminal_id' => $operation->terminal_id,
                    'occurred_at' => $operation->occurred_at->toIso8601String(),
                ],
                $this->sync->errors($company),
            ),
        ]);
    }

    /**
     * Conflits en attente d'arbitrage humain.
     */
    public function syncConflicts(Request $request): JsonResponse
    {
        $this->authorizeReports($request);

        $company = $this->company($request);

        return response()->json([
            'data' => array_map(
                static fn (RestaurantSyncConflict $conflict): array => [
                    'id' => (int) $conflict->id,
                    'restaurant_sync_operation_id' => (int) $conflict->restaurant_sync_operation_id,
                    'entity_type' => (string) $conflict->entity_type,
                    'entity_id' => $conflict->entity_id,
                    'strategy' => (string) $conflict->strategy,
                    'local_value' => $conflict->local_value,
                    'server_value' => $conflict->server_value,
                    'local_terminal_id' => $conflict->local_terminal_id,
                    'server_terminal_id' => $conflict->server_terminal_id,
                    'resolution' => $conflict->resolution,
                ],
                $this->sync->unresolvedConflicts($company),
            ),
        ]);
    }

    /**
     * Resolution EXPLICITE d'un conflit par un humain.
     */
    public function resolveConflict(Request $request, int $conflict): JsonResponse
    {
        $this->authorizeReports($request);

        $company = $this->company($request);

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:local_wins,server_wins,manual_review'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = RestaurantSyncConflict::query()
            ->where('company_id', $company->id)
            ->whereKey($conflict)
            ->first();

        if (! $model instanceof RestaurantSyncConflict) {
            throw RestaurantSyncException::operationNotInCompany($conflict);
        }

        $resolved = $this->sync->resolveConflict(
            $company,
            $model,
            RestaurantConflictStrategy::from((string) $validated['resolution']),
            $this->actorId($request),
            isset($validated['note']) ? (string) $validated['note'] : null,
        );

        return response()->json([
            'data' => [
                'id' => (int) $resolved->id,
                'resolution' => (string) $resolved->resolution,
                'resolved_at' => $resolved->resolved_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Permission d'OPERATION de salle : c'est le persona qui saisit hors reseau.
     */
    private function authorizeOperate(Request $request): void
    {
        $actor = $request->user();

        if (! $actor instanceof Employee
            || ! Gate::forUser($actor)->allows(RestaurantPermissions::SERVER)) {
            abort(403, 'RESTAURANT_SYNC_FORBIDDEN');
        }
    }

    /**
     * Permission de PILOTAGE : arbitrer un conflit engage la responsabilite du
     * restaurateur, ce n'est pas un geste de service.
     */
    private function authorizeReports(Request $request): void
    {
        $actor = $request->user();

        if (! $actor instanceof Employee
            || ! Gate::forUser($actor)->allows(RestaurantPermissions::REPORTS)) {
            abort(403, 'RESTAURANT_SYNC_FORBIDDEN');
        }
    }

    private function company(Request $request): Company
    {
        $company = $request->attributes->get('company');

        if (! $company instanceof Company) {
            abort(403, 'RESTAURANT_SYNC_NO_TENANT');
        }

        return $company;
    }

    private function actorId(Request $request): ?int
    {
        $actor = $request->user();

        if (! $actor instanceof Employee) {
            return null;
        }

        $id = $actor->getAuthIdentifier();

        return $id === null ? null : (int) $id;
    }
}
