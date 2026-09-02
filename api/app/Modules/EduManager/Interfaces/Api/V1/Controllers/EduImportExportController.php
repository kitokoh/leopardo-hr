<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduImport;
use App\Modules\EduManager\Infrastructure\Services\EduExportService;
use App\Modules\EduManager\Infrastructure\Services\EduImportService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\CommitEduImportRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\PreviewEduImportRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API import/export sécurisé EduManager — EDU-010/EDU-017 (issue #5833).
 *
 * Import : preview (aucune écriture) → commit idempotent (audité).
 * Export : CSV tenant-scopé, audit `edu_exports`, direction uniquement.
 */
class EduImportExportController extends Controller
{
    use ChecksEduSolution;

    public function __construct(
        private readonly EduImportService $imports,
        private readonly EduExportService $exports,
    ) {}

    public function preview(PreviewEduImportRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduImport::class);

        $import = $this->imports->preview($actor, $request->file('file'), $request->input('entity_type'));

        return response()->json(['data' => $this->importPayload($import)], 201);
    }

    public function commit(CommitEduImportRequest $request, EduImport $import): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($import, $actor->company_id);
        $this->authorize('update', $import);

        $committed = $this->imports->commit($actor, $import);

        return response()->json(['data' => $this->importPayload($committed)]);
    }

    public function cancel(Request $request, EduImport $import): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($import, $actor->company_id);
        $this->authorize('update', $import);

        $cancelled = $this->imports->cancel($actor, $import);

        return response()->json(['data' => $this->importPayload($cancelled)]);
    }

    public function export(Request $request, string $kind): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', EduImport::class);

        $result = $this->exports->export($actor, $kind);

        return response()->json(['data' => $result]);
    }

    /**
     * @return array<string, mixed>
     */
    private function importPayload(EduImport $import): array
    {
        return [
            'id' => (int) $import->getAttribute('id'),
            'entity_type' => $import->entity_type,
            'filename' => $import->filename,
            'status' => $import->status,
            'total_rows' => (int) $import->total_rows,
            'valid_rows' => (int) $import->valid_rows,
            'error_rows' => (int) $import->error_rows,
            'columns' => $import->columns,
            'preview_data' => $import->preview_data,
            'errors' => $import->errors,
            'committed_at' => $import->committed_at?->toIso8601String(),
        ];
    }
}
