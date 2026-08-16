<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\EdgeSync\Application\Services\EdgeLicenseService;
use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints Cloud exposés pour le déploiement et la gestion des nœuds Edge.
 *
 * Routes publiques (sans auth) :
 *   GET  /edge/download/env-example → exemple .env.edge
 *
 * Les endpoints /edge/install.sh, /edge/download/docker-compose.yml et
 * /edge/license-public-key sont servis par {@see EdgeDownloadController}
 * (installScript/dockerCompose/licensePublicKey).
 *
 * Routes nœud Edge :
 *   POST /edge/heartbeat → heartbeat depuis le nœud (no-op legacy, voir
 *                          note dans la méthode)
 *
 * NOTE (issue #1291): the platform super-admin node-management endpoints
 * (`GET/POST/DELETE /platform/edge/nodes*`) used to live on this class as
 * listNodes()/forceSync()/revokeNode(), built against a legacy bigint
 * `edge_nodes` schema (columns `node_id`, `pending_count`, `license_valid`,
 * `alert_muted`, `revoked_at`) that is never actually created in
 * production — the canonical schema created by
 * 2026_06_29_000001_create_edge_sync_tables.php (module EdgeSync DDD) uses
 * a UUID primary key, `slug`, and `metadata` JSON instead, and the legacy
 * migration (2026_06_30_000001_create_edge_nodes_table.php) neutralizes
 * itself whenever the canonical table already exists. Any real call to
 * those three methods would therefore fail with a "column ... does not
 * exist" SQL error against the tables actually created in every
 * environment. They have been removed here; the equivalent super-admin
 * node-management endpoints now live on {@see EdgeNodeController} against
 * the canonical UUID schema (see routes/api.php `platform/edge/nodes*`).
 */
class EdgeController extends Controller
{
    public function __construct(
        private readonly SyncEngineService $syncEngine,
        private readonly EdgeLicenseService $licenseService,
    ) {}

    // =========================================================================
    // Script d'installation & téléchargements
    // =========================================================================

    /** GET /edge/download/env-example */
    public function downloadEnvExample(): Response
    {
        $cloudApiUrl = config('app.url');

        $env = <<<ENV
        APP_ENV=production
        APP_KEY=
        APP_URL=http://leopardo.local
        EDGE_NODE_ID=
        EDGE_TOKEN=
        EDGE_LICENSE_PRIVATE_KEY=
        EDGE_LICENSE_PUBLIC_KEY=
        CLOUD_API_URL={$cloudApiUrl}
        ENV;

        $env = preg_replace('/^        /m', '', $env) ?? $env;

        return response($env, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=".env.edge.example"',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    // =========================================================================
    // Health check
    // =========================================================================

    /**
     * GET /edge/health
     *
     * Endpoint de sante independant du Cloud : un noeud Edge doit pouvoir
     * repondre a ce check meme lorsqu'il fonctionne en mode autonome
     * (coupure Internet). Ne depend d'aucune requete DB tenant.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'edge' => true,
            'status' => 'ok',
            'time' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * GET /edge/readiness
     *
     * #4411 : readiness = health + schéma SQLite provisionné. Le liveness
     * (`/edge/health`) reste volontairement sans DB (mode autonome offline) ;
     * ce endpoint vérifie que le schéma local existe (SELECT 1 sur sync_queue)
     * — sans lui, un nœud frais répondait « ok » avec une sync morte
     * (« no such table: sync_queue » en boucle dans le daemon).
     */
    public function readiness(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\DB::connection('sqlite')->select('SELECT 1 FROM sync_queue LIMIT 1');
        } catch (\Throwable $e) {
            return response()->json([
                'edge' => true,
                'status' => 'not_ready',
                'reason' => 'edge_schema_missing',
                'time' => Carbon::now()->toIso8601String(),
            ], 503);
        }

        return response()->json([
            'edge' => true,
            'status' => 'ok',
            'schema' => 'provisioned',
            'time' => Carbon::now()->toIso8601String(),
        ]);
    }

    // =========================================================================
    // Heartbeat Edge → Cloud
    // =========================================================================

    /**
     * POST /edge/heartbeat
     *
     * Audit #1696 : endpoint public (throttle seulement), aucun appelant dans
     * le repo (ni edge/, ni openapi.yaml). L'ancienne implémentation écrivait
     * en base avec des valeurs contrôlées par l'attaquant, sur des colonnes
     * (`node_id`, `pending_count`, `version`) absentes du schéma canonique
     * `edge_nodes` — 500 non géré ou fausses métriques. Le heartbeat
     * authentifié est `POST /api/v1/edge-node/{nodeId}/heartbeat`
     * (EdgeNodeController, token haché). Ce endpoint legacy ne modifie plus
     * aucun état : réponse purement informative.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => ['required', 'string', 'max:64'],
            'pending_count' => ['integer', 'min:0'],
            'version' => ['string', 'max:32'],
            'ip_address' => ['nullable', 'ip'],
        ]);

        Log::info('[Edge] Heartbeat received (legacy, no-op)', ['node_id' => $validated['node_id']]);

        return response()->json([
            'status' => 'ok',
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }
}
