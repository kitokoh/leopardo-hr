<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelMeterReading;
use App\Modules\FuelStation\Domain\Policies\FuelReportPolicy;
use App\Modules\FuelStation\Infrastructure\Services\FuelImportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Import/export sécurisé (FUEL-018, issue #5812).
 *
 * deny-by-default (FuelReportPolicy) : exports CSV réservés au manager,
 * audit via export_history + DataAccessAuditLogger ; imports journalisés
 * (fuel_imports), traitement asynchrone idempotent ; neutralisation OWASP
 * des formules CSV (CsvCellSanitizer).
 */
class FuelImportExportController extends Controller
{
    public function __construct(private readonly FuelImportExportService $io) {}

    public function exportSales(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->export($actor), 403);

        $from = $request->filled('from') ? (string) $request->input('from') : null;
        $to = $request->filled('to') ? (string) $request->input('to') : null;

        $rows = collect($this->io->salesRows((string) $actor->company_id, $from, $to));

        return response()->json([
            'data' => $this->io->exportCsv($request, $actor, $rows, 'fuel_sales', 'fuel_sales_export'),
        ]);
    }

    public function exportReadings(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->export($actor), 403);

        $rows = new Collection;

        if (Schema::hasTable('fuel_meter_readings')) {
            $rows = FuelMeterReading::query()
                ->where('company_id', $actor->company_id)
                ->when($request->filled('meter_id'), fn ($q) => $q->where('meter_id', $request->input('meter_id')))
                ->orderByDesc('captured_at_utc')
                ->limit(10000)
                ->get()
                ->map(fn (FuelMeterReading $r) => [
                    'id' => $r->id,
                    'meter_id' => $r->meter_id,
                    'reading_value_minor' => $r->reading_value_minor,
                    'captured_at_utc' => $r->captured_at_utc->toISOString(),
                    'status' => $r->status,
                    'source_code' => $r->source_code,
                ]);
        }

        return response()->json([
            'data' => $this->io->exportCsv($request, $actor, $rows, 'fuel_meter_readings', 'fuel_meter_readings_export'),
        ]);
    }

    public function imports(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless(app(FuelReportPolicy::class)->export($actor), 403);

        $imports = FuelImport::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($imports->items())->map(fn (FuelImport $i): array => [
                'id' => $i->id,
                'kind' => $i->kind,
                'file_name' => $i->file_name,
                'status' => $i->status,
                'total_rows' => $i->total_rows,
                'processed_rows' => $i->processed_rows,
                'failed_rows' => $i->failed_rows,
                'error_summary' => $i->error_summary,
                'created_at' => $i->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'total' => $imports->total(),
            ],
        ]);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
