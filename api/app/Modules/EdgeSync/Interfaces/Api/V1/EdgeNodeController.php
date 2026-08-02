<?php

declare(strict_types=1);

namespace App\Modules\EdgeSync\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\EdgeSync\Application\Actions\PushEdgeRecords;
use App\Modules\EdgeSync\Application\Actions\RegisterEdgeNode;
use App\Modules\EdgeSync\Application\Services\CloudDeltaBuilder;
use App\Modules\EdgeSync\Application\Services\EdgeLicenseService;
use App\Modules\EdgeSync\Application\Services\SyncEngineService;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\EdgeSync\Domain\Models\SyncQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cloud-side API consumed by:
 *   - Admin dashboard (Sanctum auth)
 *   - Edge node service (edge_token bearer auth)
 */
class EdgeNodeController extends Controller
{
    public function __construct(
        protected SyncEngineService $syncEngine,
        protected EdgeLicenseService $licenseService,
        protected CloudDeltaBuilder $deltaBuilder,
        protected RegisterEdgeNode $registerEdgeNode,
        protected PushEdgeRecords $pushEdgeRecords,
    ) {}

    // ── Admin Dashboard Routes ────────────────────────────

    /**
     * List Edge nodes for the authenticated company.
     * GET /api/v1/edge
     */
    public function index(Request $request): JsonResponse
    {
        $nodes = EdgeNode::where('company_id', $request->user()->company_id)
            ->with(['syncLogs' => fn ($q) => $q->latest()->limit(1)])
            ->get()
            ->map(fn ($node) => array_merge($node->toArray(), [
                'is_online' => $node->isOnline(),
                'license_valid' => $node->isLicenseValid(),
            ]));

        return response()->json(['data' => $nodes]);
    }

    /**
     * Register a new Edge node and issue its first license.
     * POST /api/v1/edge
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'site_address' => 'nullable|string|max:500',
            'mode' => 'in:cloud,offline,hybrid',
            'capabilities' => 'array',
        ]);

        $result = $this->registerEdgeNode->execute((int) $request->user()->company_id, $validated);

        return response()->json([
            'data' => $result['node'],
            'license' => $result['license'],
            'edge_token' => $result['edge_token'], // shown only once at registration
            'install_command' => sprintf(
                'sudo bash <(curl -fsSL %s/edge/install.sh) --node-id %s --token %s',
                config('app.url'),
                $result['node']->id,
                $result['edge_token']
            ),
        ], 201);
    }

    /**
     * Get a single Edge node.
     * GET /api/v1/edge/{nodeId}
     */
    public function show(Request $request, string $nodeId): JsonResponse
    {
        $node = EdgeNode::where('company_id', $request->user()->company_id)
            ->with(['syncLogs' => fn ($q) => $q->latest()->limit(10)])
            ->findOrFail($nodeId);

        return response()->json(['data' => $node]);
    }

    /**
     * Trigger a manual sync for an Edge node.
     * POST /api/v1/edge/{nodeId}/sync
     */
    public function sync(Request $request, string $nodeId): JsonResponse
    {
        $node = EdgeNode::where('company_id', $request->user()->company_id)
            ->findOrFail($nodeId);

        $log = $this->syncEngine->sync($node);

        return response()->json(['data' => $log]);
    }

    /**
     * Issue or renew an Edge license.
     * POST /api/v1/edge/{nodeId}/license
     */
    public function issueLicense(Request $request, string $nodeId): JsonResponse
    {
        $node = EdgeNode::where('company_id', $request->user()->company_id)
            ->findOrFail($nodeId);

        $days = (int) $request->input('valid_days', config('edge.license_validity_days', 30));
        $license = $this->licenseService->issueLicense($node, $days);

        return response()->json(['data' => $license]);
    }

    // ── Edge Node Machine Routes ──────────────────────────

    /**
     * Edge node pushes local changes to Cloud.
     * Called by the Edge daemon, not the admin UI.
     * POST /api/v1/edge-node/{nodeId}/push
     */
    public function pushFromEdge(Request $request, string $nodeId): JsonResponse
    {
        $node = EdgeNode::findOrFail($nodeId);
        $this->authorizeEdgeToken($request, $node);

        $validated = $request->validate([
            'records' => 'required|array|max:500',
            'records.*.entity_type' => 'required|string|max:100',
            'records.*.entity_id' => 'required|string|max:36',
            'records.*.operation' => 'required|in:create,update,delete',
            'records.*.payload' => 'required|array',
        ]);

        $queued = $this->pushEdgeRecords->execute($node, $validated['records']);

        return response()->json(['queued' => $queued]);
    }

    /**
     * Edge node pulls Cloud delta (changes since last sync).
     * GET /api/v1/edge-node/{nodeId}/pull
     */
    public function pullDelta(Request $request, string $nodeId): JsonResponse
    {
        $node = EdgeNode::findOrFail($nodeId);
        $this->authorizeEdgeToken($request, $node);

        $delta = $this->deltaBuilder->build($node);

        $node->update(['last_seen_at' => now()]);

        return response()->json($delta);
    }

    /**
     * Health check — called by Edge node periodically.
     * GET /api/v1/edge-node/{nodeId}/heartbeat
     */
    public function heartbeat(Request $request, string $nodeId): JsonResponse
    {
        $node = EdgeNode::findOrFail($nodeId);
        $this->authorizeEdgeToken($request, $node);

        $node->update([
            'last_seen_at' => now(),
            'local_ip' => $request->input('local_ip') ?? $node->local_ip,
            'public_ip' => $request->ip(),
        ]);

        $license = EdgeLicense::where('edge_node_id', $node->id)->first();

        return response()->json([
            'status' => 'ok',
            'server_time' => now()->toIso8601String(),
            'license_valid' => $license?->isValid() ?? false,
            'needs_renewal' => $node->needsLicenseRenewal(),
            'pending_records' => SyncQueue::where('edge_node_id', $node->id)
                ->where('status', 'pending')
                ->count(),
        ]);
    }

    /**
     * Validate a license payload (called by Edge node at startup).
     * POST /api/v1/edge-node/validate-license
     */
    public function validateLicense(Request $request): JsonResponse
    {
        $payload = $request->input('signed_payload', '');
        $result = $this->licenseService->validateLicense((string) $payload);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }

    // ── Platform Super-Admin Routes (issue #1291) ──────
    //
    // These replace the dead legacy EdgeController::listNodes()/forceSync()/
    // revokeNode() methods, which queried a bigint `edge_nodes` schema
    // (columns node_id/pending_count/license_valid/alert_muted/revoked_at)
    // that is never actually created — the real schema (created by
    // 2026_06_29_000001_create_edge_sync_tables.php) is the UUID/slug one
    // this controller's EdgeNode model already targets. Cross-tenant here
    // is intentional: these are platform-wide, super-admin-only endpoints.

    /**
     * List every Edge node across all tenants (platform overview).
     * GET /api/v1/platform/edge/nodes
     */
    public function listAllNodes(): JsonResponse
    {
        $nodes = EdgeNode::with(['company:id,name', 'syncLogs' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (EdgeNode $node) => array_merge($node->toArray(), [
                'is_online' => $node->isOnline(),
                'license_valid' => $node->isLicenseValid(),
                'company_name' => $node->company?->name,
            ]));

        return response()->json(['data' => $nodes]);
    }

    /**
     * Force a manual sync for any tenant's Edge node (platform override).
     * POST /api/v1/platform/edge/nodes/{nodeId}/sync
     */
    public function forceSync(string $nodeId): JsonResponse
    {
        $node = EdgeNode::findOrFail($nodeId);
        $log = $this->syncEngine->sync($node);

        return response()->json(['data' => $log]);
    }

    /**
     * Revoke an Edge node and its active license (platform override).
     * DELETE /api/v1/platform/edge/nodes/{nodeId}
     */
    public function revokeNode(string $nodeId): JsonResponse
    {
        $node = EdgeNode::findOrFail($nodeId);

        $node->update(['status' => 'suspended']);
        $this->licenseService->revokeLicense($node);

        return response()->json(['status' => 'revoked', 'data' => $node->fresh()]);
    }

    // ── Private Helpers ───────────────────────────────────

    private function authorizeEdgeToken(Request $request, EdgeNode $node): void
    {
        $expectedTokenHash = $node->metadata['edge_token'] ?? null;
        $providedToken = $request->bearerToken() ?? '';

        abort_unless(
            $expectedTokenHash !== null
                && $providedToken !== ''
                && hash_equals((string) $expectedTokenHash, hash('sha256', $providedToken)),
            401,
            'Invalid or missing Edge token.'
        );
    }
}
