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
 *   GET  /edge/install.sh                  → script d'installation bash
 *   GET  /edge/download/docker-compose.yml → docker-compose pré-configuré
 *   GET  /edge/license-public-key          → clé publique RS256 (PEM)
 *
 * Routes protégées (super_admin_api) :
 *   GET    /platform/edge/nodes              → liste des nœuds
 *   POST   /platform/edge/nodes/{id}/sync   → forcer sync
 *   DELETE /platform/edge/nodes/{id}         → révoquer
 *
 * Routes nœud Edge (token EDGE_TOKEN) :
 *   POST /edge/heartbeat   → heartbeat depuis le nœud
 *   POST /edge/sync        → réception sync depuis le nœud
 */
class EdgeController extends Controller
{
    public function __construct(
        private readonly SyncEngineService  $syncEngine,
        private readonly EdgeLicenseService $licenseService,
    ) {}

    // =========================================================================
    // Script d'installation & téléchargements
    // =========================================================================

    /** GET /edge/install.sh */
    public function installScript(): Response
    {
        $cloudApiUrl = config('app.url');
        $version     = config('app.version', '1.0.0');

        $script = <<<BASH
        #!/usr/bin/env bash
        # =============================================================================
        # Leopardo Edge — Script d'installation automatique
        # Généré par {$cloudApiUrl}
        # Version : {$version}
        # =============================================================================
        set -euo pipefail

        LEOPARDO_VERSION="{$version}"
        CLOUD_API_URL="{$cloudApiUrl}"
        INSTALL_DIR="\${LEOPARDO_EDGE_DIR:-/opt/leopardo-edge}"

        echo ""
        echo "╔══════════════════════════════════════════════╗"
        echo "║   Leopardo Edge — Installation               ║"
        echo "║   v\$LEOPARDO_VERSION                          ║"
        echo "╚══════════════════════════════════════════════╝"
        echo ""

        check_deps() {
            local missing=()
            for cmd in docker curl openssl; do
                command -v "\$cmd" &>/dev/null || missing+=("\$cmd")
            done
            if [ \${#missing[@]} -gt 0 ]; then
                echo "❌ Dépendances manquantes : \${missing[*]}"
                exit 1
            fi
            if ! docker compose version &>/dev/null && ! command -v docker-compose &>/dev/null; then
                echo "❌ Docker Compose introuvable."
                exit 1
            fi
        }

        check_deps

        echo "📁 Répertoire : \$INSTALL_DIR"
        mkdir -p "\$INSTALL_DIR/keys"
        cd "\$INSTALL_DIR"

        echo "⬇  Téléchargement docker-compose.yml..."
        curl -fsSL "\$CLOUD_API_URL/edge/download/docker-compose.yml" -o docker-compose.yml

        if [ ! -f .env.edge ]; then
            curl -fsSL "\$CLOUD_API_URL/edge/download/env-example" -o .env.edge
        fi

        if [ ! -f keys/edge_license_private.pem ]; then
            echo "🔑 Génération des clés RS256..."
            openssl genrsa -out keys/edge_license_private.pem 2048 2>/dev/null
            openssl rsa -in keys/edge_license_private.pem -pubout -out keys/edge_license_public.pem 2>/dev/null
        fi

        echo "🚀 Démarrage du nœud Edge..."
        if docker compose version &>/dev/null; then
            docker compose --env-file .env.edge pull --quiet
            docker compose --env-file .env.edge up -d
        else
            docker-compose --env-file .env.edge pull --quiet
            docker-compose --env-file .env.edge up -d
        fi

        echo "✅ Nœud Edge Leopardo démarré !"
        BASH;

        $script = preg_replace('/^        /m', '', $script) ?? $script;

        return response($script, 200, [
            'Content-Type'           => 'text/x-shellscript; charset=utf-8',
            'Content-Disposition'    => 'inline; filename="leopardo-edge-install.sh"',
            'Cache-Control'          => 'no-cache, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** GET /edge/download/docker-compose.yml */
    public function downloadDockerCompose(): Response
    {
        $version     = config('app.version', '1.0.0');
        $cloudApiUrl = config('app.url');

        $filePath = base_path('edge/docker-compose.yml');

        if (file_exists($filePath)) {
            return response((string) file_get_contents($filePath), 200, [
                'Content-Type'        => 'application/yaml; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="docker-compose.yml"',
                'Cache-Control'       => 'public, max-age=300',
            ]);
        }

        // Fallback généré dynamiquement
        $yaml = <<<YAML
        version: "3.9"
        services:
          edge:
            image: leopardo/edge-api:{$version}
            container_name: leopardo-edge
            restart: unless-stopped
            environment:
              CLOUD_API_URL: "\${CLOUD_API_URL:-{$cloudApiUrl}}"
              EDGE_NODE_ID:  "\${EDGE_NODE_ID}"
              EDGE_TOKEN:    "\${EDGE_TOKEN}"
        YAML;

        $yaml = preg_replace('/^        /m', '', $yaml) ?? $yaml;

        return response($yaml, 200, [
            'Content-Type'        => 'application/yaml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="docker-compose.yml"',
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }

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
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=".env.edge.example"',
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }

    /** GET /edge/license-public-key */
    public function licensePublicKey(): Response
    {
        $pem = config('edge.license_public_key', '');

        // Try file fallback
        if (empty($pem)) {
            $keyPath = base_path('edge/keys/edge_license_public.pem');
            if (file_exists($keyPath)) {
                $pem = (string) file_get_contents($keyPath);
            }
        }

        if (empty($pem)) {
            return response(
                json_encode(['error' => 'edge_public_key_not_configured']),
                503,
                ['Content-Type' => 'application/json']
            );
        }

        $pem = str_replace('\\n', "\n", $pem);

        return response($pem, 200, [
            'Content-Type'   => 'application/x-pem-file',
            'Cache-Control'  => 'public, max-age=3600',
            'X-Edge-Version' => config('app.version', '1.0.0'),
        ]);
    }

    // =========================================================================
    // Heartbeat Edge → Cloud
    // =========================================================================

    /** POST /edge/heartbeat */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id'       => ['required', 'string', 'max:64'],
            'pending_count' => ['integer', 'min:0'],
            'version'       => ['string', 'max:32'],
            'ip_address'    => ['nullable', 'ip'],
        ]);

        DB::table('edge_nodes')
            ->where('node_id', $validated['node_id'])
            ->update([
                'status'        => 'online',
                'last_seen_at'  => Carbon::now(),
                'pending_count' => $validated['pending_count'] ?? 0,
                'version'       => $validated['version'] ?? null,
                'ip_address'    => $validated['ip_address'] ?? $request->ip(),
            ]);

        Log::info('[Edge] Heartbeat reçu', ['node_id' => $validated['node_id']]);

        return response()->json([
            'status'      => 'ok',
            'server_time' => Carbon::now()->toIso8601String(),
        ]);
    }

    // =========================================================================
    // Gestion nœuds (platform super-admin)
    // =========================================================================

    /** GET /platform/edge/nodes */
    public function listNodes(): JsonResponse
    {
        $nodes = DB::table('edge_nodes as n')
            ->join('companies as c', 'c.id', '=', 'n.company_id')
            ->select([
                'n.id', 'n.node_id', 'n.name', 'n.status',
                'n.ip_address', 'n.last_seen_at', 'n.pending_count',
                'n.license_valid', 'n.license_expires_at',
                'n.alert_muted', 'n.version',
                'c.name as company_name',
            ])
            ->orderBy('n.status')
            ->orderBy('n.name')
            ->get();

        return response()->json(['data' => $nodes]);
    }

    /** POST /platform/edge/nodes/{id}/sync */
    public function forceSync(int $id): JsonResponse
    {
        $node = DB::table('edge_nodes')->find($id);
        if (! $node) {
            return response()->json(['error' => 'not_found'], 404);
        }

        DB::table('edge_nodes')->where('id', $id)->update([
            'sync_requested_at' => Carbon::now(),
        ]);

        return response()->json(['status' => 'sync_requested']);
    }

    /** DELETE /platform/edge/nodes/{id} */
    public function revokeNode(int $id): JsonResponse
    {
        $node = DB::table('edge_nodes')->find($id);
        if (! $node) {
            return response()->json(['error' => 'not_found'], 404);
        }

        DB::table('edge_nodes')->where('id', $id)->update([
            'status'     => 'revoked',
            'revoked_at' => Carbon::now(),
        ]);

        Log::warning('[Edge] Nœud révoqué', ['id' => $id, 'node_id' => $node->node_id ?? '']);

        return response()->json(['status' => 'revoked']);
    }
}
