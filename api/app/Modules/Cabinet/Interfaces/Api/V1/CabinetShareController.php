<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Domain\Models\CabinetFolder;
use App\Modules\Cabinet\Domain\Models\CabinetShare;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;
use App\Modules\Cabinet\Interfaces\Api\V1\Requests\ShareRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetShareController extends Controller
{
    /** @var array<string, class-string<CabinetFolder|CabinetDocument>> */
    private const SHAREABLE_MAP = [
        'folder' => CabinetFolder::class,
        'document' => CabinetDocument::class,
    ];

    public function __construct(private readonly CabinetService $cabinetService) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->employee($request);

        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $shares = CabinetShare::where('employee_id', $actor->id)
            ->with('shareable')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        // Pagination (#1703) : `data` reste une liste simple (contrat
        // historique des clients), les métadonnées de page sont exposées
        // dans `meta` — un paginator brut dans `data` cassait
        // `assertJsonCount(1, 'data')` et les clients (13 clés imbriquées).
        return response()->json([
            'data' => $shares->through(fn (CabinetShare $s) => $this->serialize($s))->items(),
            'meta' => [
                'current_page' => $shares->currentPage(),
                'per_page' => $shares->perPage(),
                'total' => $shares->total(),
                'last_page' => $shares->lastPage(),
            ],
        ]);
    }

    public function store(ShareRequest $request): JsonResponse
    {
        $actor = $this->employee($request);
        $data = $request->validated();

        $shareableType = is_string($data['shareable_type']) ? $data['shareable_type'] : '';
        $shareableClass = self::SHAREABLE_MAP[$shareableType];
        $shareableId = is_numeric($data['shareable_id']) ? (int) $data['shareable_id'] : 0;

        /** @var CabinetFolder|CabinetDocument $shareable */
        $shareable = $shareableClass::where('employee_id', $actor->id)
            ->findOrFail($shareableId);

        $share = $this->cabinetService->share(
            $actor,
            $shareableClass,
            $shareable->id,
            $data
        );

        $share->load('shareable');

        return response()->json([
            'data' => $this->serialize($share),
        ], 201);
    }

    public function destroy(Request $request, CabinetShare $cabinetShare): JsonResponse
    {
        $actor = $this->employee($request);

        if ($cabinetShare->employee_id !== $actor->id) {
            abort(403);
        }

        $this->cabinetService->revokeShare($cabinetShare);

        return response()->json(null, 204);
    }

    /**
     * Public endpoint: access a shared resource via token (no auth required).
     *
     * #4798 — cette route est publique (pas de middleware `tenant`) : sur un
     * worker persistant, le search_path PostgreSQL peut pointer vers le schéma
     * d'un tenant (pattern #4787) → lookup cross-tenant ou 500. On recherche
     * le partage dans CHAQUE tenant actif (schéma propre ou pool
     * shared_tenants) via TenantManager::withinTenant, et tout le chargement
     * se fait DANS le contexte tenant (les modèles ne sont jamais ré-hydratés
     * après restauration du search_path).
     *
     * @return array{expired: true}|array{type: 'document', disk: string, path: string, name: string}|array{type: 'folder', id: int, name: string, documents_count: int, children_count: int, documents: array<int, array<string, mixed>>}|null
     */
    private function resolveSharedPayload(CabinetShare $share): ?array
    {
        if ($share->isExpired()) {
            return ['expired' => true];
        }

        $shareable = $share->shareable;

        if ($shareable instanceof CabinetDocument) {
            return [
                'type' => 'document',
                'disk' => $shareable->disk,
                'path' => $shareable->path,
                'name' => $shareable->original_name,
            ];
        }

        if ($shareable instanceof CabinetFolder) {
            $documents = CabinetDocument::where('folder_id', $shareable->id)
                ->orderBy('name')
                ->get()
                ->map(fn (CabinetDocument $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'original_name' => $d->original_name,
                    'mime_type' => $d->mime_type,
                    'size' => $d->size,
                ])
                ->all();

            return [
                'type' => 'folder',
                'id' => $shareable->id,
                'name' => $shareable->name,
                'documents_count' => (int) CabinetDocument::where('folder_id', $shareable->id)->count(),
                'children_count' => (int) CabinetFolder::where('parent_id', $shareable->id)->count(),
                'documents' => $documents,
            ];
        }

        return null;
    }

    public function accessByToken(string $token): JsonResponse|StreamedResponse
    {
        $tenantManager = app(\App\Core\Tenant\TenantManager::class);
        $companies = \App\Core\Tenant\Domain\Models\Company::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $payload = null;

        foreach ($companies as $company) {
            $payload = $tenantManager->withinTenant($company, function () use ($token): ?array {
                $share = CabinetShare::query()
                    ->with('shareable')
                    ->where('share_token', $token)
                    ->first();

                if ($share === null) {
                    return null;
                }

                return $this->resolveSharedPayload($share);
            });

            if ($payload !== null) {
                break;
            }
        }

        if ($payload === null) {
            abort(404);
        }

        if (! isset($payload['type'])) {
            return response()->json(['error' => 'SHARE_EXPIRED'], 410);
        }

        if ($payload['type'] === 'document') {
            $disk = Storage::disk($payload['disk']);

            if (! $disk->exists($payload['path'])) {
                abort(404);
            }

            return $disk->download($payload['path'], $payload['name']);
        }

        return response()->json([
            'data' => [
                'type' => 'folder',
                'folder' => [
                    'id' => $payload['id'],
                    'name' => $payload['name'],
                    'documents_count' => $payload['documents_count'],
                    'children_count' => $payload['children_count'],
                ],
                'documents' => $payload['documents'],
            ],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $actor = $this->employee($request);
        $stats = $this->cabinetService->storageStats($actor);

        return response()->json(['data' => $stats]);
    }

    private function employee(Request $request): Employee
    {
        /** @var Employee $user */
        $user = $request->user();
        assert($user instanceof Employee);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CabinetShare $share): array
    {
        $shareable = $share->shareable;
        $shareableName = null;

        if ($shareable instanceof CabinetFolder || $shareable instanceof CabinetDocument) {
            $shareableName = $shareable->name;
        }

        return [
            'id' => $share->id,
            'shareable_type' => match ($share->shareable_type) {
                CabinetFolder::class => 'folder',
                CabinetDocument::class => 'document',
                default => $share->shareable_type,
            },
            'shareable_id' => $share->shareable_id,
            'shareable_name' => $shareableName,
            'share_token' => $share->share_token,
            'shared_via' => $share->shared_via,
            'shared_with_email' => $share->shared_with_email,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'created_at' => $share->created_at?->toIso8601String(),
        ];
    }
}
