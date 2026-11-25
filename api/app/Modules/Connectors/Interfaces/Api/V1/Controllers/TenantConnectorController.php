<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connectors\Domain\Exceptions\ConnectorSchemaViolationException;
use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;
use App\Modules\Connectors\Domain\Models\ConnectorAiUsage;
use App\Modules\Connectors\Domain\Models\ConnectorConnection;
use App\Modules\Connectors\Infrastructure\Services\ConnectorCatalog;
use App\Modules\Connectors\Infrastructure\Services\ConnectorConnectionTester;
use App\Modules\Connectors\Infrastructure\Services\ConnectorIntegrationIndex;
use App\Modules\Connectors\Infrastructure\Services\ConnectorSchemaValidator;
use App\Modules\Connectors\Infrastructure\Services\ConnectorAiUsageTracker;
use App\Modules\Connectors\Domain\Exceptions\ConnectorAiBudgetExceededException;
use App\Modules\Connectors\Interfaces\Api\V1\Requests\StoreTenantConnectorRequest;
use App\Modules\Connectors\Interfaces\Api\V1\Requests\UpdateTenantConnectorRequest;
use App\Modules\Connectors\Interfaces\Api\V1\Requests\UpdateTenantConnectorStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #9848 (BC-14 INTEGRATION) — onglet « Connecteurs », surface TENANT.
 *
 * Objet : un client branche SES surfaces externes — son site WordPress, sa page
 * produit Facebook, sa boutique Shopify/Instagram/TikTok Shop, sa fiche Google
 * Merchant — et retrouve au même endroit les intégrations qu'il utilisait déjà
 * (encaissements, webhooks, calendrier, pointeuses).
 *
 * Isolation : `ConnectorConnection` porte `BelongsToCompany` (scope tenant
 * fail-closed #3727). Un `findOrFail` sous scope rend un 404 cross-tenant —
 * jamais un 403 qui révélerait l'existence de la ressource d'un autre client.
 *
 * Le catalogue est filtré par `ConnectorCatalog::forAudience('tenant')` : les
 * entrées `audience = platform` (les banques de l'éditeur) n'apparaissent
 * jamais ici, même si un contrôleur l'oubliait.
 *
 * Secrets : chiffrés au repos, write-only, masqués en lecture — même contrat
 * que la surface plateforme.
 */
class TenantConnectorController extends Controller
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly ConnectorSchemaValidator $validator,
        private readonly ConnectorConnectionTester $tester,
        private readonly ConnectorIntegrationIndex $index,
        private readonly ConnectorAiUsageTracker $aiUsage,
    ) {}

    /**
     * GET /connectors/catalog — les types proposables à un tenant.
     */
    public function catalog(): JsonResponse
    {
        $items = array_map(
            static fn (ConnectorCatalogEntry $entry): array => $entry->toApi(),
            $this->catalog->forAudience('tenant'),
        );

        return new JsonResponse(['data' => ['items' => $items]]);
    }

    /**
     * GET /connectors/connections — les connexions du tenant courant.
     */
    public function index(): JsonResponse
    {
        $connections = ConnectorConnection::query()
            ->orderBy('catalog_key')
            ->orderBy('id')
            ->get();

        return new JsonResponse(['data' => ['items' => $this->presentMany($connections->all())]]);
    }

    /**
     * POST /connectors/connections — connecte une surface externe.
     */
    public function store(StoreTenantConnectorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $entry = $this->catalog->findAvailable($validated['catalog_key'], 'tenant');

        if ($entry === null) {
            return new JsonResponse([
                'error' => 'CONNECTOR_CATALOG_KEY_UNKNOWN',
                'message' => 'CONNECTOR_CATALOG_KEY_UNKNOWN',
                'localized_message' => sprintf('Le type de connecteur « %s » n\'existe pas ou n\'est pas disponible pour votre espace.', $validated['catalog_key']),
            ], 422);
        }

        try {
            $normalized = $this->validator->validate(
                $entry,
                (array) ($validated['config'] ?? []),
                (array) ($validated['secrets'] ?? []),
            );
        } catch (ConnectorSchemaViolationException $exception) {
            return $this->schemaViolation($exception);
        }

        $connection = ConnectorConnection::query()->create([
            'catalog_key' => $entry->key,
            'label' => $validated['label'],
            'status' => 'draft',
            'config' => $normalized['config'],
            'secrets' => $normalized['secrets'],
            'created_by' => $this->actorId($request),
        ]);

        return new JsonResponse(['data' => $this->present($connection)], 201);
    }

    /**
     * PATCH /connectors/connections/{id} — met à jour une connexion.
     */
    public function update(UpdateTenantConnectorRequest $request, int $id): JsonResponse
    {
        // findOrFail SOUS scope tenant : 404 cross-tenant garanti.
        $connection = ConnectorConnection::query()->findOrFail($id);

        $validated = $request->validated();

        $entry = $this->catalog->find($connection->catalog_key);

        if ($entry === null) {
            return new JsonResponse([
                'error' => 'CONNECTOR_CATALOG_ENTRY_MISSING',
                'message' => 'CONNECTOR_CATALOG_ENTRY_MISSING',
                'localized_message' => __('connectors.type_removed'),
            ], 409);
        }

        // Fusion avant validation : un PATCH partiel ne doit pas échouer sur un
        // champ requis déjà présent en base.
        $mergedConfig = array_merge($connection->config ?? [], (array) ($validated['config'] ?? []));
        $mergedSecrets = array_merge($connection->secrets ?? [], (array) ($validated['secrets'] ?? []));

        try {
            $normalized = $this->validator->validate($entry, $mergedConfig, $mergedSecrets);
        } catch (ConnectorSchemaViolationException $exception) {
            return $this->schemaViolation($exception);
        }

        $connection->fill([
            'label' => $validated['label'] ?? $connection->label,
            'config' => $normalized['config'],
            'secrets' => $normalized['secrets'],
        ])->save();

        return new JsonResponse(['data' => $this->present($connection)]);
    }

    /**
     * PATCH /connectors/connections/{id}/status — active ou révoque.
     */
    public function setStatus(UpdateTenantConnectorStatusRequest $request, int $id): JsonResponse
    {
        $connection = ConnectorConnection::query()->findOrFail($id);

        $validated = $request->validated();

        $connection->forceFill(['status' => $validated['status']])->save();

        return new JsonResponse(['data' => $this->present($connection)]);
    }

    /**
     * DELETE /connectors/connections/{id} — supprime une connexion.
     */
    public function destroy(int $id): JsonResponse
    {
        $connection = ConnectorConnection::query()->findOrFail($id);

        $connection->delete();

        return new JsonResponse(null, 204);
    }

    /**
     * POST /connectors/connections/{id}/test — vérifie réellement.
     */
    public function test(int $id): JsonResponse
    {
        $connection = ConnectorConnection::query()->findOrFail($id);
        $entry = $this->catalog->find($connection->catalog_key);

        if ($entry === null) {
            return new JsonResponse([
                'error' => 'CONNECTOR_CATALOG_ENTRY_MISSING',
                'message' => 'CONNECTOR_CATALOG_ENTRY_MISSING',
                'localized_message' => 'Ce type de connecteur n\'existe plus dans le catalogue : impossible de le tester.',
            ], 409);
        }

        $result = $this->tester->test(
            $entry,
            $connection->config ?? [],
            $connection->secrets ?? [],
        );

        $connection->forceFill([
            'last_check_status' => $result['status'],
            'last_checked_at' => now(),
            'status' => $result['status'] === 'invalid' && $connection->status === 'active'
                ? 'error'
                : $connection->status,
        ])->save();

        return new JsonResponse(['data' => [
            'connection' => $this->present($connection),
            'check' => $result,
        ]]);
    }

    /**
     * GET /connectors/connections/{id}/usage — consommation de tokens d'une
     * connexion IA pour le mois courant, et son budget.
     *
     * Réservé aux connexions `ai_*` : une connexion non-IA n'a pas de
     * consommation à suivre (422 explicite).
     */
    public function usage(int $id): JsonResponse
    {
        $connection = ConnectorConnection::query()->findOrFail($id);

        if (! str_starts_with($connection->catalog_key, 'ai_')) {
            return new JsonResponse([
                'error' => 'CONNECTOR_NOT_AI',
                'message' => 'CONNECTOR_NOT_AI',
                'localized_message' => __('connectors.not_ai'),
            ], 422);
        }

        $used = $this->aiUsage->monthlyTotal($connection);

        return new JsonResponse(['data' => [
            'connection_id' => $connection->id,
            'period' => ConnectorAiUsage::periodFor(),
            'used_tokens' => $used,
            'budget_tokens' => $this->aiUsage->monthlyBudget($connection),
            'remaining_tokens' => $this->aiUsage->monthlyBudget($connection) !== null
                ? max(0, $this->aiUsage->monthlyBudget($connection) - $used)
                : null,
        ]]);
    }

    /**
     * POST /connectors/connections/{id}/usage — enregistre la consommation
     * réelle d'un appel IA et rend le cumul du mois.
     *
     * Fail-closed : si le budget mensuel est déjà atteint, l'appel est refusé
     * (422 CONNECTOR_AI_BUDGET_EXCEEDED) AVANT tout enregistrement — on ne
     * comptabilise jamais un appel hors budget.
     */
    public function recordUsage(Request $request, int $id): JsonResponse
    {
        $connection = ConnectorConnection::query()->findOrFail($id);

        if (! str_starts_with($connection->catalog_key, 'ai_')) {
            return new JsonResponse([
                'error' => 'CONNECTOR_NOT_AI',
                'message' => 'CONNECTOR_NOT_AI',
                'localized_message' => __('connectors.not_ai'),
            ], 422);
        }

        $input = (int) ($request->input('input_tokens', 0));
        $output = (int) ($request->input('output_tokens', 0));

        if ($input < 0 || $output < 0) {
            return new JsonResponse([
                'error' => 'CONNECTOR_AI_USAGE_INVALID',
                'message' => 'CONNECTOR_AI_USAGE_INVALID',
                'localized_message' => __('connectors.usage_invalid'),
            ], 422);
        }

        try {
            $this->aiUsage->assertWithinBudget($connection);
        } catch (ConnectorAiBudgetExceededException $exception) {
            return new JsonResponse([
                'error' => ConnectorAiBudgetExceededException::ERROR_CODE,
                'message' => ConnectorAiBudgetExceededException::ERROR_CODE,
                'localized_message' => $exception->getMessage(),
                'used_tokens' => $exception->usedTokens(),
                'budget_tokens' => $exception->budgetTokens(),
            ], 422);
        }

        $totals = $this->aiUsage->record($connection, $input, $output);

        return new JsonResponse(['data' => [
            'connection_id' => $connection->id,
            'period' => ConnectorAiUsage::periodFor(),
            'input_tokens' => $totals['input_tokens'],
            'output_tokens' => $totals['output_tokens'],
            'total_tokens' => $totals['total_tokens'],
            'budget_tokens' => $this->aiUsage->monthlyBudget($connection),
        ]]);
    }

    /**
     * GET /connectors/overview — index des intégrations existantes du tenant.
     */
    public function overview(): JsonResponse
    {
        return new JsonResponse(['data' => [
            'integrations' => $this->index->forTenant(),
            'connections_count' => ConnectorConnection::query()->count(),
            'active_count' => ConnectorConnection::query()->where('status', 'active')->count(),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ConnectorConnection $connection): array
    {
        $entry = $this->catalog->find($connection->catalog_key);

        return $connection->toApi($entry?->secretFields() ?? []);
    }

    /**
     * @param  array<int, ConnectorConnection>  $connections
     * @return list<array<string, mixed>>
     */
    private function presentMany(array $connections): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = array_map(fn (ConnectorConnection $connection): array => $this->present($connection), $connections);

        return $items;
    }

    private function schemaViolation(ConnectorSchemaViolationException $exception): JsonResponse
    {
        return new JsonResponse([
            'error' => ConnectorSchemaViolationException::ERROR_CODE,
            'message' => ConnectorSchemaViolationException::ERROR_CODE,
            'localized_message' => $exception->getMessage(),
            'violations' => $exception->violations(),
        ], 422);
    }

    private function actorId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id !== null ? (int) $id : null;
    }
}