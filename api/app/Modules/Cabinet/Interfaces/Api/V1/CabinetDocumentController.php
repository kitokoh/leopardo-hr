<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CabinetDocumentResource;
use App\Modules\Cabinet\Domain\Models\CabinetDocument;
use App\Modules\Cabinet\Infrastructure\Services\CabinetService;
use App\Modules\Cabinet\Interfaces\Api\V1\Requests\MoveDocumentRequest;
use App\Modules\Cabinet\Interfaces\Api\V1\Requests\StoreDocumentRequest;
use App\Modules\Cabinet\Interfaces\Api\V1\Requests\UpdateDocumentRequest;
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
        $filters = $request->validate([
            'folder_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'root_only' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ]);

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

        if (array_key_exists('folder_id', $filters) && $filters['folder_id'] !== null) {
            $query->where('folder_id', $filters['folder_id']);
        } elseif (($filters['root_only'] ?? false) === true) {
            $query->whereNull('folder_id');
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('original_name', 'ilike', "%{$search}%");
            });
        }

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return CabinetDocumentResource::collection($paginated)->response();
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $actor = $this->employee($request);
        $file = $request->file('file');
        assert($file instanceof UploadedFile);

        $document = $this->cabinetService->uploadDocument($actor, $file, $request->validated());

        return (new CabinetDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, CabinetDocument $cabinetDocument): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        return (new CabinetDocumentResource($cabinetDocument))->response();
    }

    public function update(UpdateDocumentRequest $request, CabinetDocument $cabinetDocument): JsonResponse
    {
        $this->authorizeOwnership($request, $cabinetDocument);

        $document = $this->cabinetService->updateDocument($cabinetDocument, $request->validated());

        return (new CabinetDocumentResource($document))->response();
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

        return (new CabinetDocumentResource($document))->response();
    }

    private function employee(Request $request): Employee
    {
        /** @var Employee $user */
        $user = $request->user();
        assert($user instanceof Employee);

        return $user;
    }

    private function authorizeOwnership(Request $request, CabinetDocument $document): void
    {
        $actor = $this->employee($request);

        if ($document->employee_id !== $actor->id) {
            abort(403);
        }
    }
}
