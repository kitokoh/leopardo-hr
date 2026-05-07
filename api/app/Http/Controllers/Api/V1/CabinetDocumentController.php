<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Cabinet\MoveDocumentRequest;
use App\Http\Requests\Api\V1\Cabinet\StoreDocumentRequest;
use App\Http\Requests\Api\V1\Cabinet\UpdateDocumentRequest;
use App\Models\CabinetDocument;
use App\Models\Employee;
use App\Services\CabinetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetDocumentController extends Controller
{
    public function __construct(private readonly CabinetService $cabinetService) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->employee($request);

        $query = CabinetDocument::query()
            ->select([
                'id',
                'company_id',
                'folder_id',
                'name',
                'original_name',
                'mime_type',
                'size',
                'notes',
                'created_at',
                'updated_at',
            ])
            ->where('employee_id', $actor->id);

        if ($request->has('folder_id')) {
            $folderId = $request->input('folder_id');
            $query->where('folder_id', $folderId);
        } elseif ($request->boolean('root_only', false)) {
            $query->whereNull('folder_id');
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('original_name', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 20);
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $paginated->map(fn (CabinetDocument $d) => $this->serialize($d)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $actor = $this->employee($request);
        $file = $request->file('file');
        assert($file instanceof UploadedFile);

        $document = $this->cabinetService->uploadDocument($actor, $file, $request->validated());

        return response()->json([
            'data' => $this->serialize($document),
        ], 201);
    }

    public function show(Request $request, CabinetDocument $cabinetDocument): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        return response()->json([
            'data' => $this->serialize($cabinetDocument),
        ]);
    }

    public function update(UpdateDocumentRequest $request, CabinetDocument $cabinetDocument): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        $document = $this->cabinetService->updateDocument($cabinetDocument, $request->validated());

        return response()->json([
            'data' => $this->serialize($document),
        ]);
    }

    public function destroy(Request $request, CabinetDocument $cabinetDocument): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        $this->cabinetService->deleteDocument($cabinetDocument);

        return response()->json(null, 204);
    }

    public function download(Request $request, CabinetDocument $cabinetDocument): StreamedResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        $disk = Storage::disk($cabinetDocument->disk);

        if (! $disk->exists($cabinetDocument->path)) {
            abort(404);
        }

        return $disk->download($cabinetDocument->path, $cabinetDocument->original_name);
    }

    public function move(MoveDocumentRequest $request, CabinetDocument $cabinetDocument): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        /** @var int|null $folderId */
        $folderId = $request->validated('folder_id');

        $document = $this->cabinetService->moveDocument($cabinetDocument, $folderId);

        return response()->json([
            'data' => $this->serialize($document),
        ]);
    }

    private function employee(Request $request): Employee
    {
        $user = $request->user();
        assert($user instanceof Employee);

        return $user;
    }

    private function authorizeOwnership(Request $request, CabinetDocument $document): void
    {
        $actor = $this->employee($request);

        if ($document->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($document->employee_id !== $actor->id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CabinetDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'folder_id' => $doc->folder_id,
            'name' => $doc->name,
            'original_name' => $doc->original_name,
            'mime_type' => $doc->mime_type,
            'size' => $doc->size,
            'notes' => $doc->notes,
            'created_at' => $doc->created_at?->toIso8601String(),
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];
    }
}
