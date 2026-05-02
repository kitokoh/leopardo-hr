<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cabinet\ShareRequest;
use App\Models\CabinetDocument;
use App\Models\CabinetFolder;
use App\Models\CabinetShare;
use App\Services\CabinetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetShareController extends Controller
{
    private const SHAREABLE_MAP = [
        'folder' => CabinetFolder::class,
        'document' => CabinetDocument::class,
    ];

    public function __construct(private readonly CabinetService $cabinetService) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $shares = CabinetShare::where('employee_id', $actor->id)
            ->with('shareable')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $shares->map(fn (CabinetShare $s) => $this->serialize($s)),
        ]);
    }

    public function store(ShareRequest $request): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();

        $shareableClass = self::SHAREABLE_MAP[$data['shareable_type']];
        $shareable = $shareableClass::where('employee_id', $actor->id)
            ->findOrFail($data['shareable_id']);

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
        $actor = $request->user();

        if ($cabinetShare->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($cabinetShare->employee_id !== $actor->id) {
            abort(403);
        }

        $this->cabinetService->revokeShare($cabinetShare);

        return response()->json(null, 204);
    }

    /**
     * Public endpoint: access a shared resource via token (no auth required).
     */
    public function accessByToken(string $token): JsonResponse|StreamedResponse
    {
        $share = CabinetShare::where('share_token', $token)->firstOrFail();

        if ($share->isExpired()) {
            return response()->json(['error' => 'SHARE_EXPIRED'], 410);
        }

        $shareable = $share->shareable;

        if ($shareable instanceof CabinetDocument) {
            $disk = Storage::disk($shareable->disk);

            if (! $disk->exists($shareable->path)) {
                abort(404);
            }

            return $disk->download($shareable->path, $shareable->original_name);
        }

        if ($shareable instanceof CabinetFolder) {
            $folder = $shareable;
            $folder->loadCount(['documents', 'children']);

            $documents = CabinetDocument::where('folder_id', $folder->id)
                ->orderBy('name')
                ->get()
                ->map(fn (CabinetDocument $d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'original_name' => $d->original_name,
                    'mime_type' => $d->mime_type,
                    'size' => $d->size,
                ]);

            return response()->json([
                'data' => [
                    'type' => 'folder',
                    'folder' => [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'documents_count' => $folder->documents_count,
                        'children_count' => $folder->children_count,
                    ],
                    'documents' => $documents,
                ],
            ]);
        }

        abort(404);
    }

    public function stats(Request $request): JsonResponse
    {
        $actor = $request->user();
        $stats = $this->cabinetService->storageStats($actor);

        return response()->json(['data' => $stats]);
    }

    private function serialize(CabinetShare $share): array
    {
        $shareable = $share->shareable;

        return [
            'id' => $share->id,
            'shareable_type' => match ($share->shareable_type) {
                CabinetFolder::class => 'folder',
                CabinetDocument::class => 'document',
                default => $share->shareable_type,
            },
            'shareable_id' => $share->shareable_id,
            'shareable_name' => $shareable?->name ?? null,
            'share_token' => $share->share_token,
            'shared_via' => $share->shared_via,
            'shared_with_email' => $share->shared_with_email,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'created_at' => $share->created_at?->toIso8601String(),
        ];
    }
}
