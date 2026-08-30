<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Infrastructure\Services\FuelImportService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\StoreFuelImportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Imports CSV sécurisés FuelStation (FUEL-018, #5812).
 *
 * Manager + solution active (fail-closed) + tenant-scoped. Preview
 * (dry-run) sans écriture ; rollback logique (zéro écriture si une ligne
 * est invalide) ; audit par ligne.
 */
class FuelImportController extends Controller
{
    public function __construct(
        private readonly FuelImportService $service,
    ) {}

    public function store(StoreFuelImportRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelImport::class);

        $dryRun = $request->boolean('dry_run');

        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $result = $this->service->importCsv(
                actor: $actor,
                importType: (string) $request->input('import_type'),
                file: $file,
                dryRun: $dryRun,
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'FUEL_IMPORT_INVALID',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $this->payload($result['import']),
            'preview' => $result['preview'],
            'applied' => $result['applied'],
            'dry_run' => $dryRun,
        ], $result['applied'] ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelImport::class);

        $query = FuelImport::query()->where('company_id', $actor->company_id);

        if ($request->filled('import_type')) {
            $query->where('import_type', $request->input('import_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $imports = $query->orderByDesc('created_at')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($imports->items())->map(fn (FuelImport $import): array => $this->payload($import)),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'total' => $imports->total(),
            ],
        ]);
    }

    public function show(Request $request, FuelImport $import): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($import->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->authorize('view', $import);

        return response()->json(['data' => $this->payload($import)]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }

    /** @return array<string, mixed> */
    private function payload(FuelImport $import): array
    {
        return [
            'id' => $import->id,
            'import_type' => $import->import_type,
            'filename' => $import->filename,
            'status' => $import->status,
            'total_lines' => $import->total_lines,
            'valid_lines' => $import->valid_lines,
            'error_lines' => $import->error_lines,
            'errors' => $import->errors,
            'created_at' => $import->created_at?->toISOString(),
        ];
    }
}
