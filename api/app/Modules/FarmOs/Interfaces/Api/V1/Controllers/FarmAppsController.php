<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmConflictStrategy;
use App\Modules\FarmOs\Domain\Enums\FarmOfflineOperationType;
use App\Modules\FarmOs\Domain\Exceptions\FarmSyncException;
use App\Modules\FarmOs\Domain\Models\FarmSyncConflict;
use App\Modules\FarmOs\Domain\Services\FarmAppCatalogueService;
use App\Modules\FarmOs\Domain\Services\FarmAssistantContextService;
use App\Modules\FarmOs\Domain\Services\FarmSyncService;
use App\Modules\FarmOs\Interfaces\Api\V1\Requests\FarmSyncSubmitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T5.1 / T5.2 / T6.1 / T6.2 : applications, hors ligne, assistant et
 * integrations externes.
 *
 * HONNETETE : ce controleur n'expose que des fonctions REELLEMENT implementees.
 *   - le catalogue applicatif ne fabrique aucun lien de telechargement ;
 *   - le mode hors ligne n'accepte que le registre ferme d'operations ;
 *   - l'assistant ne fournit que des donnees internes du tenant ;
 *   - la matrice d'integrations declare explicitement ce qui n'est PAS branche.
 */
final class FarmAppsController extends Controller
{
    public function __construct(
        private readonly FarmAppCatalogueService $catalogue,
        private readonly FarmSyncService $sync,
        private readonly FarmAssistantContextService $assistant,
    ) {}

    /**
     * T5.1 — Centre « Mes logiciels » : outils actives, plateformes et acces reels.
     */
    public function catalogue(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => $this->catalogue->catalogue($company),
        ]);
    }

    /**
     * T5.1 — Outils recommandes pour un profil donne, restreints aux outils
     * REELLEMENT actives.
     */
    public function toolsForRole(Request $request, string $role): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => [
                'role' => $role,
                'tools' => $this->catalogue->toolsForRole($company, $role),
            ],
        ]);
    }

    /**
     * T5.2 — Pousse un lot d'operations realisees hors reseau.
     *
     * Chaque operation est traitee independamment : une operation refusee
     * n'empeche pas les autres d'etre enregistrees, et elle reste visible dans
     * l'historique des erreurs.
     */
    public function submitOperations(FarmSyncSubmitRequest $request): JsonResponse
    {
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

            $type = FarmOfflineOperationType::tryFrom($operationType);

            // Registre FERME : une operation hors ligne non prevue est REFUSEE et
            // conservee, jamais appliquee « au mieux ».
            if ($type === null) {
                $rejected = $this->sync->rejectUnsupported(
                    $company,
                    $clientOperationId,
                    $operationType,
                    $payload,
                    $occurredAt,
                );

                $results[] = [
                    'client_operation_id' => $clientOperationId,
                    'status' => $rejected->status,
                    'error_code' => $rejected->last_error,
                ];

                continue;
            }

            $outcome = $this->sync->submit(
                $company,
                $clientOperationId,
                $type,
                $payload,
                $occurredAt,
                isset($entry['device_id']) ? (int) $entry['device_id'] : null,
                $request->user()?->getAuthIdentifier() !== null ? (int) $request->user()->getAuthIdentifier() : null,
            );

            $results[] = [
                'client_operation_id' => $clientOperationId,
                'status' => $outcome['operation']->status,
                'replayed' => $outcome['replayed'],
                'strategy' => $this->sync->strategyFor($type)->value,
                'conflict_id' => $outcome['conflict']?->id,
            ];
        }

        return response()->json(['data' => ['results' => $results]], 202);
    }

    /**
     * T5.2 — Etat de la file de synchronisation (pour le tableau de bord).
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => $this->sync->statusSummary($company),
        ]);
    }

    /**
     * T5.2 — HISTORIQUE DES ERREURS : operations refusees ou en echec.
     */
    public function syncErrors(Request $request): JsonResponse
    {
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
                    'occurred_at' => $operation->occurred_at->toIso8601String(),
                ],
                $this->sync->errors($company),
            ),
        ]);
    }

    /**
     * T5.2 — Conflits en attente d'arbitrage humain.
     */
    public function syncConflicts(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => array_map(
                static fn (FarmSyncConflict $conflict): array => [
                    'id' => (int) $conflict->id,
                    'farm_sync_operation_id' => (int) $conflict->farm_sync_operation_id,
                    'entity_type' => (string) $conflict->entity_type,
                    'strategy' => (string) $conflict->strategy,
                    'local_value' => $conflict->local_value,
                    'server_value' => $conflict->server_value,
                    'resolution' => $conflict->resolution,
                ],
                $this->sync->unresolvedConflicts($company),
            ),
        ]);
    }

    /**
     * T5.2 — Resolution EXPLICITE d'un conflit par un humain.
     */
    public function resolveConflict(Request $request, int $conflict): JsonResponse
    {
        $company = $this->company($request);

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:local_wins,server_wins,manual_review'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = FarmSyncConflict::query()
            ->where('company_id', $company->id)
            ->whereKey($conflict)
            ->first();

        if (! $model instanceof FarmSyncConflict) {
            throw FarmSyncException::operationNotInCompany($conflict);
        }

        $resolved = $this->sync->resolveConflict(
            $company,
            $model,
            FarmConflictStrategy::from((string) $validated['resolution']),
            $request->user()?->getAuthIdentifier() !== null ? (int) $request->user()->getAuthIdentifier() : null,
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
     * T6.1 — Contexte interne fourni a l'assistant agricole.
     *
     * Ce contexte ne contient QUE des donnees du tenant courant, et il declare
     * explicitement les donnees externes indisponibles.
     */
    public function assistantContext(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => $this->assistant->buildContext($company),
        ]);
    }

    /**
     * T6.1 — Questions a poser a un conseiller agricole (jamais des
     * recommandations presentees comme certaines).
     */
    public function advisorQuestions(Request $request): JsonResponse
    {
        $company = $this->company($request);

        return response()->json([
            'data' => [
                'questions' => $this->assistant->advisorQuestions($company),
            ],
        ]);
    }

    /**
     * T6.2 — Matrice des integrations externes : ce qui est branche, ce qui ne
     * l'est pas, et a quel cout. Aucune API payante n'est activee.
     */
    public function integrations(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'integrations' => [
                    [
                        'code' => 'weather',
                        'available' => false,
                        'cost' => 'none',
                        'reason' => 'FARM_INTEGRATION_NOT_CONNECTED',
                    ],
                    [
                        'code' => 'mapping',
                        'available' => false,
                        'cost' => 'none',
                        'reason' => 'FARM_INTEGRATION_NOT_CONNECTED',
                    ],
                    [
                        'code' => 'satellite_imagery',
                        'available' => false,
                        'cost' => 'none',
                        'reason' => 'FARM_INTEGRATION_NOT_CONNECTED',
                    ],
                    [
                        'code' => 'sensor_telemetry',
                        'available' => false,
                        'cost' => 'none',
                        'reason' => 'FARM_INTEGRATION_NOT_CONNECTED',
                    ],
                    [
                        'code' => 'market_prices',
                        'available' => false,
                        'cost' => 'none',
                        'reason' => 'FARM_INTEGRATION_NOT_CONNECTED',
                    ],
                ],
                'policy' => 'manual_entry_first',
            ],
        ]);
    }

    private function company(Request $request): Company
    {
        /** @var Company $company */
        $company = $request->attributes->get('current_company') ?? app('current_company');

        return $company;
    }
}
