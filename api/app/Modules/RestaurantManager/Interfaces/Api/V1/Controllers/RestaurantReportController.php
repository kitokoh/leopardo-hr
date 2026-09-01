<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Application\Actions\ExportRestaurantReportAction;
use App\Modules\RestaurantManager\Application\Services\RestaurantReportService;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\RestaurantReportQueryRequest;
use App\Modules\RestaurantManager\Interfaces\Api\V1\Requests\StoreRestaurantReportExportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * RESTO-701/702/703 (#6214/#6215/#6216) — Rapports & pilotage restaurant.
 *
 * Tous les endpoints exigent la permission documentaire `restaurant.reports`
 * (matrice RBAC RESTO-306 : principal, rh, manager, server, kitchen, rider).
 * Les agrégats sont calculés serveur (RestaurantReportService), jamais
 * acceptés du client. L'export CSV est idempotent avec URL signée éphémère.
 */
class RestaurantReportController extends Controller
{
    public function __construct(
        private readonly RestaurantReportService $reports,
        private readonly ExportRestaurantReportAction $exportAction,
    ) {
    }

    public function sales(RestaurantReportQueryRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        $data = $this->reports->sales(
            $this->companyId($request),
            $this->from($request),
            $this->to($request),
            $request->integer('branch_id') ?: null,
        );

        return response()->json(['data' => $data]);
    }

    public function occupancy(RestaurantReportQueryRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        return response()->json(['data' => $this->reports->occupancy(
            $this->companyId($request),
            $this->from($request),
            $this->to($request),
            $request->integer('branch_id') ?: null,
        )]);
    }

    public function products(RestaurantReportQueryRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        return response()->json(['data' => $this->reports->products(
            $this->companyId($request),
            $this->from($request),
            $this->to($request),
            $request->integer('branch_id') ?: null,
        )]);
    }

    public function cogs(RestaurantReportQueryRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        return response()->json(['data' => $this->reports->cogs(
            $this->companyId($request),
            $this->from($request),
            $this->to($request),
            $request->integer('branch_id') ?: null,
        )]);
    }

    public function pos(RestaurantReportQueryRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        return response()->json(['data' => $this->reports->pos(
            $this->companyId($request),
            $this->from($request),
            $this->to($request),
            $request->integer('branch_id') ?: null,
        )]);
    }

    public function kpis(RestaurantReportQueryRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        return response()->json(['data' => $this->reports->kpis(
            $this->companyId($request),
            $request->integer('branch_id') ?: null,
        )]);
    }

    public function export(StoreRestaurantReportExportRequest $request): JsonResponse
    {
        $this->assertReportsPermission($request);

        $result = $this->exportAction->export(
            $request->string('report_type')->toString(),
            $this->companyId($request),
            $this->from($request),
            $this->to($request),
            $request->integer('branch_id') ?: null,
        );

        return response()->json(['data' => $result]);
    }

    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired download signature.');
        }

        $filename = basename((string) $request->query('export', ''));
        $relative = 'restaurant/exports/'.$actor->company_id.'/'.$filename;
        $path = Storage::disk('local')->path($relative);

        if ($filename === '' || ! is_file($path)) {
            return response()->json(['message' => 'Export not found.'], 404);
        }

        return response()->download($path, $filename, ['Content-Type' => 'text/csv']);
    }

    private function assertReportsPermission(Request $request): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager', 'server', 'kitchen', 'rider')) {
            abort(403);
        }
    }

    private function companyId(Request $request): string
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return $actor->company_id;
    }

    private function from(Request $request): ?\Illuminate\Support\Carbon
    {
        $from = $request->query('from');

        return is_string($from) && $from !== '' ? \Illuminate\Support\Carbon::parse($from) : null;
    }

    private function to(Request $request): ?\Illuminate\Support\Carbon
    {
        $to = $request->query('to');

        return is_string($to) && $to !== '' ? \Illuminate\Support\Carbon::parse($to) : null;
    }
}
