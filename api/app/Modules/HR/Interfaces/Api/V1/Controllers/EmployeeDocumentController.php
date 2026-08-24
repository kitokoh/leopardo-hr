<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployeeDocumentResource;
use App\Modules\HR\Domain\Models\EmployeeDocument;
use App\Modules\HR\Interfaces\Api\V1\Requests\StoreEmployeeDocumentRequest;
use App\Modules\HR\Interfaces\Api\V1\Requests\UpdateEmployeeDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Checklist documents du dossier employé (issue #5326 — gap G3).
 *
 * RBAC :
 *   - écriture (store/update/destroy) : principal/rh uniquement ;
 *   - lecture manager : tous les dossiers du tenant (filtres employé/type) ;
 *   - lecture employé : SON dossier uniquement via GET /me/documents.
 *
 * Isolation tenant portée par le trait BelongsToCompany (scope global +
 * company_id auto-rempli, garde fail-closed #3727) : un document d'un autre
 * tenant répond 404, jamais de fuite cross-tenant.
 */
class EmployeeDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $query = EmployeeDocument::query()->with('employee:id,first_name,last_name,email');

        if ($actor->isManager()) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->integer('employee_id'));
            }
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
        } else {
            $query->where('employee_id', $actor->id);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));

        return EmployeeDocumentResource::collection(
            $query->orderByDesc('document_date')->orderByDesc('id')->paginate($perPage)
        );
    }

    public function store(StoreEmployeeDocumentRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $validated['status'] ??= EmployeeDocument::STATUS_RECEIVED;
        $validated['uploaded_by'] = $actor->id;

        /** @var EmployeeDocument $document */
        $document = EmployeeDocument::query()->create($validated);

        return (new EmployeeDocumentResource($document))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $employeeDocument): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $document = EmployeeDocument::query()->findOrFail((int) $employeeDocument);

        if (! $actor->isManager() && $document->employee_id !== $actor->id) {
            abort(403);
        }

        return (new EmployeeDocumentResource($document))->response();
    }

    public function update(UpdateEmployeeDocumentRequest $request, string $employeeDocument): JsonResponse
    {
        $document = EmployeeDocument::query()->findOrFail((int) $employeeDocument);

        $document->update($request->validated());

        return (new EmployeeDocumentResource($document->fresh()))->response();
    }

    public function destroy(Request $request, string $employeeDocument): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $document = EmployeeDocument::query()->findOrFail((int) $employeeDocument);
        $document->delete();

        return response()->json(['message' => __('hr_documents.deleted')]);
    }

    /**
     * Self-service employé : lecture seule de SON dossier.
     */
    public function myDocuments(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $perPage = max(1, min(100, $request->integer('per_page', 20)));

        return EmployeeDocumentResource::collection(
            EmployeeDocument::query()
                ->forEmployee($actor->id)
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->paginate($perPage)
        );
    }
}
