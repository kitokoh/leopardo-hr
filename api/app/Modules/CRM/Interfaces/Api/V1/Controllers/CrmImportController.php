<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Enums\CrmImportEntityType;
use App\Modules\CRM\Domain\Exceptions\CrmImportException;
use App\Modules\CRM\Domain\Models\CrmImport;
use App\Modules\CRM\Application\Actions\CrmImportService;
use App\Modules\CRM\Interfaces\Api\V1\Requests\StoreCrmImportRequest;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmImportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #5714 — API d'import CSV CRM (cycle preview → commit/cancel).
 *
 * Toutes les routes vivent sous `/api/v1/crm/imports`, protégées par
 * `CrmImportPolicy` + contexte tenant (404 sûr hors tenant via le scope
 * global `BelongsToCompany`).
 */
class CrmImportController extends Controller
{
    public function __construct(private readonly CrmImportService $service)
    {
    }

    /**
     * Upload + preview : parse et valide le fichier SANS écrire dans les
     * tables cibles. Retourne 201 + session `previewed`.
     */
    public function store(StoreCrmImportRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', CrmImport::class)) {
            abort(403);
        }

        $file = $request->file('file');

        if (! $file instanceof \Illuminate\Http\UploadedFile) {
            abort(422, 'Un fichier CSV est requis.');
        }

        try {
            $import = $this->service->preview(
                $file,
                CrmImportEntityType::from($request->input('entity_type')),
                $actor,
            );
        } catch (CrmImportException $e) {
            return $this->importError($e);
        }

        return (new CrmImportResource($import))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Commit explicite : claim atomique puis job de persistance.
     * Idempotent — un second commit répond 409.
     */
    public function commit(Request $request, CrmImport $crmImport): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        // 404 sûr cross-tenant (binding {crmImport} précède le middleware
        // tenant — le scope global ne filtre pas encore à ce stade).
        if ($actor->company_id !== $crmImport->company_id) {
            abort(404);
        }

        if ($actor->cannot('commit', $crmImport)) {
            abort(403);
        }

        try {
            $import = $this->service->commit($crmImport->id, $actor);
        } catch (CrmImportException $e) {
            return $this->importError($e);
        }

        return (new CrmImportResource($import))->response();
    }

    /**
     * Annulation avant commit.
     */
    public function cancel(Request $request, CrmImport $crmImport): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $crmImport->company_id) {
            abort(404);
        }

        if ($actor->cannot('cancel', $crmImport)) {
            abort(403);
        }

        try {
            $import = $this->service->cancel($crmImport->id, $actor);
        } catch (CrmImportException $e) {
            return $this->importError($e);
        }

        return (new CrmImportResource($import))->response();
    }

    /**
     * Détail d'une session d'import.
     */
    public function show(Request $request, CrmImport $crmImport): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $crmImport->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $crmImport)) {
            abort(403);
        }

        return (new CrmImportResource($crmImport))->response();
    }

    private function importError(CrmImportException $e): JsonResponse
    {
        return new JsonResponse([
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422);
    }
}
