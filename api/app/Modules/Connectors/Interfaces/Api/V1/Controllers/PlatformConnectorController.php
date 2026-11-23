<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Modules\Connectors\Domain\Exceptions\ConnectorSchemaViolationException;
use App\Modules\Connectors\Domain\Models\ConnectorCatalogEntry;
use App\Modules\Connectors\Domain\Models\PlatformConnectorConnection;
use App\Modules\Connectors\Infrastructure\Services\ConnectorCatalog;
use App\Modules\Connectors\Infrastructure\Services\ConnectorConnectionTester;
use App\Modules\Connectors\Infrastructure\Services\ConnectorIntegrationIndex;
use App\Modules\Connectors\Infrastructure\Services\ConnectorSchemaValidator;
use App\Modules\Connectors\Interfaces\Api\V1\Requests\StorePlatformConnectorRequest;
use App\Modules\Connectors\Interfaces\Api\V1\Requests\UpdatePlatformConnectorRequest;
use App\Modules\Connectors\Interfaces\Api\V1\Requests\UpdatePlatformConnectorStatusRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #9847 (BC-14 INTEGRATION) — onglet « Connecteurs », surface SUPERADMIN.
 *
 * Objet : connecter dynamiquement les comptes externes de la PLATEFORME —
 * d'abord ses banques (celles qui reçoivent les paiements des tenants), qui
 * étaient jusqu'ici saisies en dur dans la configuration d'environnement.
 *
 * Contrat de sécurité (aligné sur `PlatformPaymentGatewayController`) :
 *  - les secrets sont chiffrés au repos et ne redescendent JAMAIS en clair :
 *    l'API ne renvoie qu'un masque (`sk_live_••••1234`) — write-only ;
 *  - un secret absent ou vide au PATCH conserve la valeur en base, pour qu'on
 *    puisse corriger un libellé sans retaper un IBAN ;
 *  - chaque écriture est auditée SANS valeur de secret (ni ancienne ni
 *    nouvelle) dans le journal ;
 *  - permissions `connectors.view` / `connectors.manage`.
 *
 * Le formulaire n'est pas codé ici : il est DÉCRIT par `field_schema` du
 * catalogue, et `ConnectorSchemaValidator` valide la saisie contre lui. Ajouter
 * un type de connecteur est donc une écriture de données.
 */
class PlatformConnectorController extends Controller
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
        private readonly ConnectorSchemaValidator $validator,
        private readonly ConnectorConnectionTester $tester,
        private readonly ConnectorIntegrationIndex $index,
    ) {}

    /**
     * GET /platform/connectors/catalog — les types de connecteur proposables à
     * la plateforme, avec leur schéma de champs.
     */
    public function catalog(): JsonResponse
    {
        $items = array_map(
            static fn (ConnectorCatalogEntry $entry): array => $entry->toApi(),
            $this->catalog->forAudience('platform'),
        );

        return new JsonResponse(['data' => ['items' => $items]]);
    }

    /**
     * GET /platform/connectors/connections — les connexions de la plateforme.
     */
    public function index(): JsonResponse
    {
        $connections = PlatformConnectorConnection::query()
            ->orderByDesc('is_default')
            ->orderBy('catalog_key')
            ->orderBy('id')
            ->get();

        return new JsonResponse(['data' => ['items' => $this->presentMany($connections->all())]]);
    }

    /**
     * POST /platform/connectors/connections — connecte un nouveau compte.
     */
    public function store(StorePlatformConnectorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $entry = $this->catalog->findAvailable($validated['catalog_key'], 'platform');

        if ($entry === null) {
            // 422 et non 404 : la clé est un champ de formulaire, pas une
            // ressource. Un 404 ferait croire à une route inexistante.
            return $this->unknownCatalogKey($validated['catalog_key']);
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

        $connection = PlatformConnectorConnection::query()->create([
            'catalog_key' => $entry->key,
            'label' => $validated['label'],
            'status' => 'draft',
            'config' => $normalized['config'],
            'secrets' => $normalized['secrets'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'created_by' => $this->actorId($request),
        ]);

        $this->audit($request, 'connector.created', $connection, null, [
            'catalog_key' => $connection->catalog_key,
            'label' => $connection->label,
            'config' => $connection->config,
            // On journalise QUELLES clés secrètes ont été fournies, jamais
            // leur valeur.
            'secrets_provided' => array_keys($normalized['secrets']),
        ]);

        return new JsonResponse(['data' => $this->present($connection)], 201);
    }

    /**
     * PATCH /platform/connectors/connections/{id} — met à jour une connexion.
     *
     * Les secrets sont write-only : un secret absent ou vide conserve la valeur
     * en place. Un secret fourni REMPLACE l'ancien.
     */
    public function update(UpdatePlatformConnectorRequest $request, int $id): JsonResponse
    {
        $connection = PlatformConnectorConnection::query()->findOrFail($id);

        $validated = $request->validated();

        $entry = $this->catalog->find($connection->catalog_key);

        if ($entry === null) {
            // Le type a été retiré du catalogue : on refuse la modification
            // plutôt que d'écrire une configuration que plus rien ne décrit.
            return new JsonResponse([
                'error' => 'CONNECTOR_CATALOG_ENTRY_MISSING',
                'message' => 'CONNECTOR_CATALOG_ENTRY_MISSING',
                'localized_message' => 'Ce type de connecteur n\'existe plus dans le catalogue : la connexion ne peut plus être modifiée.',
            ], 409);
        }

        // Fusion AVANT validation : un PATCH partiel ne doit pas être refusé
        // parce qu'un champ requis n'est pas renvoyé (il est déjà en base).
        $mergedConfig = array_merge($connection->config ?? [], (array) ($validated['config'] ?? []));
        $mergedSecrets = array_merge($connection->secrets ?? [], (array) ($validated['secrets'] ?? []));

        try {
            $normalized = $this->validator->validate($entry, $mergedConfig, $mergedSecrets);
        } catch (ConnectorSchemaViolationException $exception) {
            return $this->schemaViolation($exception);
        }

        $before = [
            'label' => $connection->label,
            'config' => $connection->config,
            'is_default' => $connection->is_default,
        ];

        $connection->fill([
            'label' => $validated['label'] ?? $connection->label,
            'config' => $normalized['config'],
            'secrets' => $normalized['secrets'],
            'is_default' => (bool) ($validated['is_default'] ?? $connection->is_default),
        ])->save();

        $this->audit($request, 'connector.updated', $connection, $before, [
            'label' => $connection->label,
            'config' => $connection->config,
            'secrets_changed' => array_keys((array) ($validated['secrets'] ?? [])),
        ]);

        return new JsonResponse(['data' => $this->present($connection)]);
    }

    /**
     * PATCH /platform/connectors/connections/{id}/status — active, révoque…
     */
    public function setStatus(UpdatePlatformConnectorStatusRequest $request, int $id): JsonResponse
    {
        $connection = PlatformConnectorConnection::query()->findOrFail($id);

        $validated = $request->validated();

        $before = ['status' => $connection->status];
        $connection->forceFill(['status' => $validated['status']])->save();

        $this->audit($request, 'connector.status_changed', $connection, $before, [
            'status' => $connection->status,
        ]);

        return new JsonResponse(['data' => $this->present($connection)]);
    }

    /**
     * DELETE /platform/connectors/connections/{id} — supprime une connexion.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $connection = PlatformConnectorConnection::query()->findOrFail($id);

        $before = [
            'catalog_key' => $connection->catalog_key,
            'label' => $connection->label,
        ];

        $connection->delete();

        $this->audit($request, 'connector.deleted', $connection, $before, null);

        return new JsonResponse(null, 204);
    }

    /**
     * POST /platform/connectors/connections/{id}/test — vérifie réellement.
     *
     * Le verdict est celui du contrôle exécuté (`ConnectorConnectionTester`) :
     * jamais un succès inventé. Le résultat est persisté pour que l'écran
     * affiche le dernier état connu sans relancer un test à chaque ouverture.
     */
    public function test(int $id): JsonResponse
    {
        $connection = PlatformConnectorConnection::query()->findOrFail($id);
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
            // Un test en échec fait passer une connexion active en `error` :
            // l'écran doit refléter la réalité, pas l'intention.
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
     * GET /platform/connectors/overview — index des intégrations existantes.
     *
     * LECTURE seule : chaque intégration garde son écran natif comme source de
     * vérité. L'onglet les regroupe, il ne les duplique pas.
     */
    public function overview(): JsonResponse
    {
        return new JsonResponse(['data' => [
            'integrations' => $this->index->forPlatform(),
            'connections_count' => PlatformConnectorConnection::query()->count(),
            'active_count' => PlatformConnectorConnection::query()->where('status', 'active')->count(),
        ]]);
    }

    /**
     * Présente une connexion : config non secrète + masques des secrets.
     *
     * @return array<string, mixed>
     */
    private function present(PlatformConnectorConnection $connection): array
    {
        $entry = $this->catalog->find($connection->catalog_key);

        return $connection->toApi($entry?->secretFields() ?? []);
    }

    /**
     * @param  array<int, PlatformConnectorConnection>  $connections
     * @return list<array<string, mixed>>
     */
    private function presentMany(array $connections): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = array_map(fn (PlatformConnectorConnection $connection): array => $this->present($connection), $connections);

        return $items;
    }

    private function unknownCatalogKey(string $key): JsonResponse
    {
        return new JsonResponse([
            'error' => 'CONNECTOR_CATALOG_KEY_UNKNOWN',
            'message' => 'CONNECTOR_CATALOG_KEY_UNKNOWN',
            'localized_message' => sprintf('Le type de connecteur « %s » n\'existe pas ou n\'est pas disponible pour la plateforme.', $key),
        ], 422);
    }

    private function schemaViolation(ConnectorSchemaViolationException $exception): JsonResponse
    {
        return new JsonResponse([
            'error' => ConnectorSchemaViolationException::ERROR_CODE,
            'message' => ConnectorSchemaViolationException::ERROR_CODE,
            'localized_message' => $exception->getMessage(),
            // Le détail par champ est ce qui rend l'erreur actionnable : sans
            // lui, l'utilisateur ne sait pas quel champ corriger.
            'violations' => $exception->violations(),
        ], 422);
    }

    private function actorId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Journalise une écriture SANS valeur de secret.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(Request $request, string $action, PlatformConnectorConnection $connection, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'company_id' => null,
            'user_id' => $this->actorId($request),
            'action' => $action,
            'module' => 'connectors',
            'auditable_type' => 'platform_connector_connection',
            'auditable_id' => $connection->id,
            'old_values' => $before,
            'new_values' => $after,
        ]);
    }
}
