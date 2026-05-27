<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cabinet\StoreFolderRequest;
use App\Http\Requests\Api\V1\Cabinet\UpdateFolderRequest;
use App\Models\CabinetFolder;
use App\Models\Employee;
use App\Services\CabinetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CabinetFolderController extends Controller
{
    public function __construct(private readonly CabinetService $cabinetService) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->employee($request);
        $parentId = $request->input('parent_id');

        $query = CabinetFolder::where('employee_id', $actor->id);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        $folders = $query->withCount(['documents', 'children'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $folders->map(fn (CabinetFolder $f) => $this->serialize($f)),
        ]);
    }

    public function store(StoreFolderRequest $request): JsonResponse
    {
        $actor = $this->employee($request);

        if ($request->filled('parent_id')) {
            CabinetFolder::where('employee_id', $actor->id)
                ->findOrFail($request->integer('parent_id'));
        }

        $folder = $this->cabinetService->createFolder($actor, $request->validated());

        return response()->json([
            'data' => $this->serialize($folder),
        ], 201);
    }

    public function show(Request $request, CabinetFolder $cabinetFolder): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetFolder);

        $cabinetFolder->loadCount(['documents', 'children']);

        return response()->json([
            'data' => $this->serialize($cabinetFolder),
        ]);
    }

    public function update(UpdateFolderRequest $request, CabinetFolder $cabinetFolder): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetFolder);

        $folder = $this->cabinetService->updateFolder($cabinetFolder, $request->validated());
        $folder->loadCount(['documents', 'children']);

        return response()->json([
            'data' => $this->serialize($folder),
        ]);
    }

    public function destroy(Request $request, CabinetFolder $cabinetFolder): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetFolder);

        $this->cabinetService->deleteFolder($cabinetFolder);

        return response()->json(null, 204);
    }

    private function employee(Request $request): Employee
    {
        /** @var Employee $user */
        $user = $request->user();
        assert($user instanceof Employee);

        return $user;
    }

    private function authorizeOwnership(Request $request, CabinetFolder $folder): void
    {
        $actor = $this->employee($request);

        if ($folder->employee_id !== $actor->id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CabinetFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'color' => $folder->color,
            'icon' => $folder->icon,
            'documents_count' => $folder->documents_count ?? 0,
            'children_count' => $folder->children_count ?? 0,
            'created_at' => $folder->created_at?->toIso8601String(),
            'updated_at' => $folder->updated_at?->toIso8601String(),
        ];
    }
}
