<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Application\Actions\CreateCrmExport;
use App\Modules\CRM\Application\DTOs\CreateCrmExportDTO;
use App\Modules\CRM\Domain\Exceptions\CrmExportException;
use App\Modules\CRM\Domain\Exceptions\CrmExportExpiredException;
use App\Modules\CRM\Domain\Exceptions\CrmExportNotFoundException;
use App\Modules\CRM\Domain\Exceptions\CrmExportNotReadyException;
use App\Modules\CRM\Domain\Models\CrmExportJob;
use App\Modules\CRM\Infrastructure\Services\CrmReadModelService;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmExportRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmExportJobResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports CRM asynchrones + read models (issue #5729).
 *
 * RBAC : api.manager:principal,rh. Jobs tenant-scoped, colonnes
 * allowlistées, accès expirant (`expires_at`), audit des téléchargements.
 */
class CrmExportController extends Controller
{
    public function __construct(private readonly CreateCrmExport $createCrmExport) {}

    /** @return AnonymousResourceCollection<int, CrmExportJobResource> */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CrmExportJob::query();

        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        return CrmExportJobResource::collection(
            $query->orderByDesc('created_at')->paginate((int) $request->input('per_page', 25)),
        );
    }

    public function store(StoreCrmExportRequest $request): JsonResponse
    {
        $dto = CreateCrmExportDTO::fromArray($request->validated());

        try {
            $job = $this->createCrmExport->execute($dto, (string) ($request->user()?->id ?? ''));
        } catch (CrmExportException $e) {
            return new JsonResponse(['error' => $e->errorCode()], $e->httpStatus());
        }

        return (new CrmExportJobResource($job))->response()->setStatusCode(201);
    }

    public function show(string $export): JsonResponse
    {
        $job = $this->jobOrFail($export);

        return (new CrmExportJobResource($job))->response();
    }

    public function download(string $export): StreamedResponse|JsonResponse
    {
        $job = $this->jobOrFail($export);

        if ($job->status !== 'completed') {
            throw new CrmExportNotReadyException();
        }

        if ($job->expires_at !== null && $job->expires_at->isPast()) {
            $job->forceFill(['status' => 'expired'])->save();

            throw new CrmExportExpiredException();
        }

        $path = (string) $job->file_path;
        if ($path === '' || ! Storage::disk('private')->exists($path)) {
            throw new CrmExportNotReadyException();
        }

        Log::info('CRM export: téléchargement', [
            'export_job_id' => $job->id,
            'user_id' => auth()->id(),
        ]);

        return Storage::disk('private')->download($path, $job->file_name ?? 'export.csv');
    }

    public function readModels(CrmReadModelService $service): JsonResponse
    {
        return new JsonResponse(['data' => $service->overview()]);
    }

    private function jobOrFail(string $exportId): CrmExportJob
    {
        $job = CrmExportJob::query()->where('id', $exportId)->first();
        if ($job === null) {
            throw new CrmExportNotFoundException();
        }

        return $job;
    }
}
