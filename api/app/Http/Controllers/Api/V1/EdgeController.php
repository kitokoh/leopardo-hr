<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

/**
 * Endpoints Cloud exposés pour le déploiement et la gestion des nœuds Edge.
 *
 * Routes publiques (sans auth) :
 *   GET  /edge/install.sh                  → script d'installation bash
 *   GET  /edge/download/docker-compose.yml → docker-compose pré-configuré
 *   GET  /edge/license-public-key          → clé publique RS256 (PEM)
 *
 * Routes protégées (super_admin_api) :
 *   GET  /platform/edge/nodes              → liste des nœuds
 *   POST /platform/edge/nodes/{id}/sync    → forcer sync
 *   DELETE /platform/edge/nodes/{id}       → révoquer
 *
 * Routes nœud Edge (token EDGE_TOKEN) :
 *   POST /edge/heartbeat                   → heartbeat depuis le nœud
 *   POST /edge/sync                        → réception sync depuis le nœud
 */
class EdgeController extends Controller
{
    // =========================================================================
    // 5.2 — Script d'installation
    // =========================================================================

    /**
     * GET /edge/install.sh
     *
     * Retourne un script bash prêt à l'emploi pour installer un nœud Edge.
     * Usage sur le serveur cible :
     *   curl -fsSL https://api.leopardo-rh.com/edge/install.sh | bash
     */
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

        # ── Prérequis ──────────────────────────────────────────────────────
        check_deps() {
            local missing=()
            for cmd in docker curl openssl; do
                command -v "\$cmd" &>/dev/null || missing+=("\$cmd")
            done
            if [ \${#missing[@]} -gt 0 ]; then
                echo "❌ Dépendances manquantes : \${missing[*]}"
                echo "   Installez-les puis relancez ce script."
                exit 1
            fi
            # Docker Compose (v2 plugin ou standalone)
            if ! docker compose version &>/dev/null && ! command -v docker-compose &>/dev/null; then
                missing+=("docker-compose")
                echo "❌ Docker Compose introuvable. Installez Docker Desktop ou le plugin Compose."
                exit 1
            fi
        }

        check_deps

        # ── Répertoire d'installation ──────────────────────────────────────
        echo "📁 Répertoire : \$INSTALL_DIR"
        mkdir -p "\$INSTALL_DIR/keys"
        cd "\$INSTALL_DIR"

        # ── Téléchargement docker-compose.yml ─────────────────────────────
        echo "⬇  Téléchargement docker-compose.yml..."
        curl -fsSL "\$CLOUD_API_URL/edge/download/docker-compose.yml" -o docker-compose.yml

        # ── Variables d'environnement ──────────────────────────────────────
        if [ ! -f .env.edge ]; then
            echo "⬇  Téléchargement .env.edge.example..."
            curl -fsSL "\$CLOUD_API_URL/edge/download/env-example" -o .env.edge
            echo ""
            echo "⚠  Fichier .env.edge créé. Vous devez renseigner :"
            echo "   - EDGE_NODE_ID"
            echo "   - EDGE_TOKEN"
            echo "   - CLOUD_API_URL"
            echo "   - EDGE_LICENSE_PRIVATE_KEY / PUBLIC_KEY"
            echo ""
        else
            echo "✅ .env.edge existant conservé."
        fi

        # ── Génération clés RS256 si absentes ─────────────────────────────
        if [ ! -f keys/edge_license_private.pem ]; then
            echo "🔑 Génération des clés RS256..."
            openssl genrsa -out keys/edge_license_private.pem 2048 2>/dev/null
            openssl rsa -in keys/edge_license_private.pem -pubout -out keys/edge_license_public.pem 2>/dev/null
            echo "✅ Clés générées dans ./keys/"
            echo "   → Ajoutez le contenu de keys/edge_license_public.pem"
            echo "     dans EDGE_LICENSE_PUBLIC_KEY de votre .env.edge"
        else
            echo "✅ Clés RS256 existantes conservées."
        fi

        # ── Démarrage ─────────────────────────────────────────────────────
        echo ""
        echo "🚀 Démarrage du nœud Edge..."
        if docker compose version &>/dev/null; then
            docker compose --env-file .env.edge pull --quiet
            docker compose --env-file .env.edge up -d
        else
            docker-compose --env-file .env.edge pull --quiet
            docker-compose --env-file .env.edge up -d
        fi

        echo ""
        echo "╔══════════════════════════════════════════════╗"
        echo "║  ✅ Nœud Edge Leopardo démarré !             ║"
        echo "║                                              ║"
        echo "║  Interface web : http://leopardo.local       ║"
        echo "║  API locale    : http://leopardo.local/api   ║"
        echo "║                                              ║"
        echo "║  Logs : docker compose logs -f               ║"
        echo "╚══════════════════════════════════════════════╝"
        echo ""
        BASH;

        // Dé-indenter (heredoc ajoute des tabs)
        $script = preg_replace('/^        /m', '', $script);

        return response($script, 200, [
            'Content-Type'        => 'text/x-shellscript; charset=utf-8',
            'Content-Disposition' => 'inline; filename="leopardo-edge-install.sh"',
            'Cache-Control'       => 'no-cache, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // =========================================================================
    // 5.2 — docker-compose.yml téléchargeable
    // =========================================================================

    /**
     * GET /edge/download/docker-compose.yml
     */
    public function downloadDockerCompose(): Response
    {
        $version     = config('app.version', '1.0.0');
        $cloudApiUrl = config('app.url');

        $yaml = <<<YAML
        # =============================================================================
        # Leopardo Edge Node — docker-compose.yml (v{$version})
        # Téléchargé depuis {$cloudApiUrl}
        # =============================================================================

        version: "3.9"

        services:
          edge:
            image: leopardo/edge-api:{$version}
            container_name: leopardo-edge
            restart: unless-stopped
            ports:
              - "80:80"
              - "443:443"
            volumes:
              - edge_data:/data
              - ./keys:/app/storage/keys:ro
            environment:
              APP_ENV:                  "\${APP_ENV:-production}"
              APP_DEBUG:                "\${APP_DEBUG:-false}"
              APP_KEY:                  "\${APP_KEY}"
              APP_URL:                  "\${APP_URL:-http://leopardo.local}"
              EDGE_ENABLED:             "true"
              EDGE_NODE_ID:             "\${EDGE_NODE_ID}"
              EDGE_TOKEN:               "\${EDGE_TOKEN}"
              EDGE_LICENSE_PRIVATE_KEY: "\${EDGE_LICENSE_PRIVATE_KEY}"
              EDGE_LICENSE_PUBLIC_KEY:  "\${EDGE_LICENSE_PUBLIC_KEY}"
              EDGE_LICENSE_TTL_DAYS:    "\${EDGE_LICENSE_TTL_DAYS:-30}"
              CLOUD_API_URL:            "\${CLOUD_API_URL}"
              EDGE_SILENCE_THRESHOLD_MINUTES: "\${EDGE_SILENCE_THRESHOLD_MINUTES:-30}"
              DB_CONNECTION:            "sqlite"
              DB_DATABASE:              "/data/leopardo_edge.sqlite"
              CACHE_STORE:              "file"
              QUEUE_CONNECTION:         "sync"
              SESSION_DRIVER:           "file"
              MAIL_MAILER:              "\${MAIL_MAILER:-log}"
              MAIL_HOST:                "\${MAIL_HOST:-}"
              MAIL_PORT:                "\${MAIL_PORT:-587}"
              MAIL_USERNAME:            "\${MAIL_USERNAME:-}"
              MAIL_PASSWORD:            "\${MAIL_PASSWORD:-}"
              MAIL_FROM_ADDRESS:        "\${MAIL_FROM_ADDRESS:-edge@leopardo-rh.com}"
              SERVER_NAME:              "\${SERVER_NAME:-leopardo.local, :80}"
            healthcheck:
              test: ["CMD", "wget", "-qO-", "http://localhost/api/v1/edge/health"]
              interval: 30s
              timeout: 5s
              retries: 3
              start_period: 20s
            logging:
              driver: "json-file"
              options:
                max-size: "10m"
                max-file: "3"

        volumes:
          edge_data:
            driver: local
        YAML;

        $yaml = preg_replace('/^        /m', '', $yaml);

        return response($yaml, 200, [
            'Content-Type'        => 'application/yaml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="docker-compose.yml"',
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }

    /**
     * GET /edge/download/env-example
     */
    public function downloadEnvExample(): Response
    {
        $cloudApiUrl = config('app.url');

        $env = <<<ENV
        # Leopardo Edge Node — Variables d'environnement
        # Généré par {$cloudApiUrl}

        APP_ENV=production
        APP_DEBUG=false
        APP_KEY=
        APP_URL=http://leopardo.local

        EDGE_NODE_ID=
        EDGE_TOKEN=
        EDGE_LICENSE_PRIVATE_KEY=
        EDGE_LICENSE_PUBLIC_KEY=
        EDGE_LICENSE_TTL_DAYS=30

        CLOUD_API_URL={$cloudApiUrl}
        EDGE_SILENCE_THRESHOLD_MINUTES=30

        SERVER_NAME=leopardo.local, :80

        MAIL_MAILER=log
        MAIL_HOST=
        MAIL_PORT=587
        MAIL_USERNAME=
        MAIL_PASSWORD=
        MAIL_FROM_ADDRESS=edge@leopardo-rh.com
        ENV;

        $env = preg_replace('/^        /m', '', $env);

        return response($env, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=".env.edge.example"',
            'Cache-Control'       => 'public, max-age=300',
        ]);
    }

    // =========================================================================
    // 5.3 — Clé publique RS256
    // =========================================================================

    /**
     * GET /edge/license-public-key
     *
     * Retourne la clé publique RS256 au format PEM.
     * Utilisée par les apps Flutter/mobile pour vérifier les licences Edge.
     */
    public function licensePublicKey(): Response
    {
        $pem = config('edge.license_public_key');

        if (empty($pem)) {
            return response(
                json_encode(['error' => 'edge_public_key_not_configured']),
                503,
                ['Content-Type' => 'application/json']
            );
        }

        // Normaliser : PEM en ligne ou valeur multiline
        $pem = str_replace('\\n', "\n", $pem);

        return response($pem, 200, [
            'Content-Type'  => 'application/x-pem-file',
            'Cache-Control' => 'public, max-age=3600',
            'X-Edge-Version' => config('app.version', '1.0.0'),
        ]);
    }

    // =========================================================================
    // Heartbeat Edge → Cloud
    // =========================================================================

    /**
     * POST /edge/heartbeat
     *
     * Reçoit le ping d'un nœud Edge actif.
     * Auth: Bearer EDGE_TOKEN (middleware edge.token)
     */
    public function heartbeat(Request $request): \Illuminate\Http\JsonResponse
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
                'status'         => 'online',
                'last_seen_at'   => Carbon::now(),
                'pending_count'  => $validated['pending_count'] ?? 0,
                'version'        => $validated['version'] ?? null,
                'ip_address'     => $validated['ip_address'] ?? $request->ip(),
            ]);

        Log::info('[Edge] Heartbeat reçu', ['node_id' => $validated['node_id']]);

        return response()->json(['status' => 'ok', 'server_time' => Carbon::now()->toIso8601String()]);
    }

    // =========================================================================
    // Gestion nœuds (platform super-admin)
    // =========================================================================

    /** GET /platform/edge/nodes */
    public function listNodes(): \Illuminate\Http\JsonResponse
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
    public function forceSync(int $id): \Illuminate\Http\JsonResponse
    {
        $node = DB::table('edge_nodes')->find($id);
        if (! $node) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // Marque le nœud comme "sync demandé" — le nœud récupérera l'instruction
        // à son prochain heartbeat
        DB::table('edge_nodes')->where('id', $id)->update([
            'sync_requested_at' => Carbon::now(),
        ]);

        return response()->json(['status' => 'sync_requested']);
    }

    /** DELETE /platform/edge/nodes/{id} */
    public function revokeNode(int $id): \Illuminate\Http\JsonResponse
    {
        $node = DB::table('edge_nodes')->find($id);
        if (! $node) {
            return response()->json(['error' => 'not_found'], 404);
        }

        DB::table('edge_nodes')->where('id', $id)->update([
            'status'     => 'revoked',
            'revoked_at' => Carbon::now(),
        ]);

        Log::warning('[Edge] Nœud révoqué', ['id' => $id, 'node_id' => $node->node_id]);

        return response()->json(['status' => 'revoked']);
    }
}
