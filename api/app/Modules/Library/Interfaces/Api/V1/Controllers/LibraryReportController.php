<?php

declare(strict_types=1);

namespace App\Modules\Library\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Library\Domain\Enums\LibraryResourceStatus;
use App\Modules\Library\Domain\Models\LibraryReport;
use App\Modules\Library\Domain\Models\LibraryResource;
use App\Modules\Library\Interfaces\Api\V1\Requests\StoreLibraryReportRequest;
use Illuminate\Http\JsonResponse;

/**
 * Signalement public d'un contenu de la bibliothèque (procédure de retrait).
 *
 * Route isolée (`throttle:shop-public`, SANS auth) :
 *   POST /public/library/{slug}/reports
 *
 * Le signalement est enregistré avec le statut `open` ; le traitement
 * (retrait du contenu) est manuel, côté administration. Aucune donnée
 * personnelle n'est exigée (email facultatif).
 */
class LibraryReportController extends Controller
{
    public function store(StoreLibraryReportRequest $request, string $slug): JsonResponse
    {
        $resource = LibraryResource::query()
            ->whereNull('company_id')
            ->where('slug', $slug)
            ->where('status', LibraryResourceStatus::Published->value)
            ->first();

        if (! $resource instanceof LibraryResource) {
            abort(404);
        }

        $report = LibraryReport::query()->create([
            'resource_id' => $resource->id,
            'reason' => $request->validated('reason'),
            'details' => $request->validated('details'),
            'reporter_email' => $request->validated('reporter_email'),
            'status' => 'open',
        ]);

        return response()->json(['data' => ['id' => $report->id, 'status' => $report->status]], 201);
    }
}
