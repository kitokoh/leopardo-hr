<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Interfaces\Api\V1\Controllers;

use App\Jobs\GenerateDeliveryExportJob;
use App\Modules\Delivery\Domain\Models\DeliveryExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV async des livraisons (BC-26-D07, issue #6295).
 *
 * POST crée une ligne `pending` + dispatch le job (pattern
 * GenerateBankExportJob) ; GET observe le statut (done → URL de téléchargement
 * bornée) ; le téléchargement est scopé tenant. RBAC manager|admin.
 */
final class DeliveryAsyncExportController
{
    private const DEFAULT_DAYS = 30;

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        [$from, $to] = $this->dateRange($request);

        /** @var DeliveryExport $export */
        $export = DeliveryExport::query()->create([
            'company_id' => $companyId,
            'status' => 'pending',
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'requested_by' => $request->user()?->id,
        ]);

        GenerateDeliveryExportJob::dispatch((int) $export->id);

        return response()->json([
            'data' => [
                'id' => $export->id,
                'status' => $export->status,
                'from_date' => $export->from_date->toDateString(),
                'to_date' => $export->to_date->toDateString(),
                'created_at' => $export->created_at?->toIso8601String(),
            ],
        ], 202);
    }

    public function show(Request $request, int $export): JsonResponse
    {
        $found = $this->findExport($export, $this->companyId($request));

        return response()->json([
            'data' => [
                'id' => $found->id,
                'status' => $found->status,
                'from_date' => $found->from_date->toDateString(),
                'to_date' => $found->to_date->toDateString(),
                'filename' => $found->filename,
                'error_message' => $found->error_message,
                'completed_at' => $found->completed_at?->toIso8601String(),
                'download_url' => $found->status === 'done'
                    ? url(sprintf('/api/v1/delivery/deliveries/reports/async-export/%d/download', $found->id))
                    : null,
            ],
        ]);
    }

    public function download(Request $request, int $export): StreamedResponse
    {
        $found = $this->findExport($export, $this->companyId($request));

        if ($found->status !== 'done' || $found->filename === null) {
            abort(409, 'EXPORT_NOT_READY');
        }

        if (! Storage::disk('local')->exists($found->filename)) {
            abort(404, 'EXPORT_FILE_MISSING');
        }

        return Storage::disk('local')->download($found->filename, basename($found->filename));
    }

    private function findExport(int $exportId, string $companyId): DeliveryExport
    {
        $export = DeliveryExport::query()
            ->where('company_id', $companyId)
            ->whereKey($exportId)
            ->first();

        if ($export === null) {
            abort(404);
        }

        return $export;
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function dateRange(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse((string) $request->string('to'))->endOfDay()
            : now();

        $from = $request->filled('from')
            ? Carbon::parse((string) $request->string('from'))->startOfDay()
            : (clone $to)->subDays(self::DEFAULT_DAYS);

        if ($from->greaterThan($to)) {
            abort(422, 'INVALID_DATE_RANGE');
        }

        return [$from, $to];
    }

    private function companyId(Request $request): string
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }
}
