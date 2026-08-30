<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelExportAsset;
use App\Modules\TravelAgency\Infrastructure\Jobs\ExportTravelReportJob;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelExportRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Resources\TravelExportAssetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-505 (#6075) — Export CSV idempotent + URL signée éphémère.
 * POST /travel/reports/export → asset + job (202) ; GET de l'asset →
 * URL signée si `generated`. Rejeu même clé → même export (aucun doublon).
 */
class TravelExportController extends Controller
{
    public function store(StoreTravelExportRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('travel.reports')) {
            abort(403);
        }

        $data = $request->validated();

        $asset = TravelExportAsset::query()->firstOrCreate(
            [
                'company_id' => $actor->company_id,
                'idempotency_key' => (string) $data['idempotency_key'],
            ],
            [
                'report_type' => (string) $data['report_type'],
                'from_at' => $data['from'],
                'to_at' => $data['to'],
                'status' => TravelExportAsset::STATUS_PENDING,
                'created_by_user_id' => $actor->id,
            ],
        );

        if ($asset->status === TravelExportAsset::STATUS_FAILED) {
            $asset->forceFill(['status' => TravelExportAsset::STATUS_PENDING])->save();
        }

        if ($asset->wasRecentlyCreated || $asset->status === TravelExportAsset::STATUS_PENDING) {
            ExportTravelReportJob::dispatch($asset->id);
        }

        return (new TravelExportAssetResource($asset))->response()->setStatusCode(202);
    }

    public function show(Request $request, TravelExportAsset $travelExportAsset): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelExportAsset->company_id) {
            abort(404);
        }

        if ($actor->cannot('travel.reports')) {
            abort(403);
        }

        return (new TravelExportAssetResource($travelExportAsset))->response();
    }
}
