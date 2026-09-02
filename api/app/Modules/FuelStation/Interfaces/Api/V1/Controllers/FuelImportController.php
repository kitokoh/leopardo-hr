<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Domain\Models\FuelImport;
use App\Modules\FuelStation\Domain\Models\FuelReportSnapshot;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use App\Modules\FuelStation\Infrastructure\Services\FuelImportService;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportingService;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\CommitFuelImportRequest;
use App\Modules\FuelStation\Interfaces\Api\V1\Requests\PreviewFuelImportRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Import/export sécurisé FuelStation (FUEL-018, issue #5812).
 *
 * - POST /fuel-station/imports/preview : preview CSV (aucun effet sur les
 *   tables cibles, validation ligne par ligne, limites taille/lignes).
 * - POST /fuel-station/imports/{import}/commit : commit idempotent avec
 *   rollback logique (transactiion) ; rejeu → état existant.
 * - POST /fuel-station/imports/{import}/cancel : annulation avant commit.
 * - GET /fuel-station/imports : historique (manager).
 * - GET /fuel-station/reports/{type}/export : CSV contrôlé depuis les
 *   snapshots FUEL-017 (borné, sans PII, URL signée).
 *
 * Manager uniquement ; isolation tenant fail-closed (404).
 */
class FuelImportController extends Controller
{
    private const MAX_UPLOAD_BYTES = 2097152; // 2 Mo

    private const MAX_ROWS = 5000;

    public function __construct(
        private readonly FuelImportService $imports,
        private readonly FuelReportingService $reports,
    ) {}

    public function preview(PreviewFuelImportRequest $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('create', FuelImport::class);

        $entityTypeRaw = $request->input('entity_type');
        $entityType = is_string($entityTypeRaw) ? $entityTypeRaw : '';
        $file = $request->file('file');

        if ($file === null || $file->getSize() > self::MAX_UPLOAD_BYTES) {
            abort(422, 'FILE_TOO_LARGE');
        }

        $content = (string) $file->get();
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        $totalLines = count($lines);

        if ($totalLines > self::MAX_ROWS + 1) {
            abort(422, 'TOO_MANY_ROWS');
        }

        $headers = array_map(static fn (mixed $h): string => (string) $h, str_getcsv((string) array_shift($lines)));
        $rows = array_map(static function (string $line) use ($headers): array {
            $values = str_getcsv($line);
            $padded = array_pad($values, count($headers), '');

            /** @var array<string, string> $combined */
            $combined = array_combine($headers, $padded);

            return $combined;
        }, $lines);

        $result = $this->imports->preview($actor, $entityType, (string) $file->getClientOriginalName(), $rows, $headers);

        return response()->json([
            'data' => [
                'id' => $result['import']->id,
                'entity_type' => $result['import']->entity_type,
                'filename' => $result['import']->filename,
                'status' => $result['import']->status,
                'total_rows' => $result['import']->total_rows,
                'valid_rows' => $result['import']->valid_rows,
                'error_rows' => $result['import']->error_rows,
                'preview' => $result['import']->preview_data,
            ],
            'meta' => ['errors' => $result['errors']],
        ]);
    }

    public function commit(CommitFuelImportRequest $request, FuelImport $import): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($import, $actor);
        $this->authorize('update', FuelImport::class);

        $import = $this->imports->commit($import, $actor);

        return response()->json(['data' => $this->importPayload($import)]);
    }

    public function cancel(Request $request, FuelImport $import): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertTenantOwned($import, $actor);
        $this->authorize('update', FuelImport::class);

        $import = $this->imports->cancel($import, $actor);

        return response()->json(['data' => $this->importPayload($import)]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', FuelImport::class);

        $query = FuelImport::query()
            ->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        $imports = $query
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($imports->items())->map(fn (FuelImport $import): array => $this->importPayload($import)),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'last_page' => $imports->lastPage(),
                'total' => $imports->total(),
                'per_page' => $imports->perPage(),
            ],
        ]);
    }

    public function export(Request $request, string $type): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewReports', FuelReportSnapshot::class);

        if (! in_array($type, FuelReportSnapshot::TYPES, true)) {
            abort(404, 'SNAPSHOT_TYPE_UNKNOWN');
        }

        $station = FuelStation::query()
            ->where('company_id', $actor->company_id)
            ->find($request->integer('station_id'));

        if (! $station instanceof FuelStation) {
            abort(404);
        }

        $periodStartRaw = $request->input('period_start') ?? now()->startOfMonth()->toDateString();
        $periodEndRaw = $request->input('period_end') ?? now()->toDateString();
        $periodStart = is_string($periodStartRaw) ? $periodStartRaw : now()->startOfMonth()->toDateString();
        $periodEnd = is_string($periodEndRaw) ? $periodEndRaw : now()->toDateString();

        $result = $this->reports->snapshot($station, $type, $periodStart, $periodEnd, $actor);
        $csv = $this->toCsv($result['snapshot']->payload);

        $filename = 'fuel-'.$type.'-'.$station->id.'-'.Carbon::now('UTC')->format('YmdHis').'.csv';
        Storage::disk('local')->put('exports/'.$filename, $csv);

        return response()->json([
            'data' => [
                'filename' => $filename,
                'url' => Storage::disk('local')->url('exports/'.$filename),
                'rows' => max(0, count($result['snapshot']->payload) - 1),
            ],
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function toCsv(array $payload): string
    {
        $rows = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $rows[] = [$key, json_encode($value, JSON_THROW_ON_ERROR)];
            } else {
                $rows[] = [$key, is_scalar($value) ? (string) $value : ''];
            }
        }

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);

        return (string) stream_get_contents($output);
    }

    /** @return array<string, mixed> */
    private function importPayload(FuelImport $import): array
    {
        return [
            'id' => $import->id,
            'entity_type' => $import->entity_type,
            'filename' => $import->filename,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'error_rows' => $import->error_rows,
            'result' => $import->result,
            'created_by' => $import->created_by,
            'committed_by' => $import->committed_by,
            'committed_at' => $import->committed_at?->toIso8601String(),
            'cancelled_at' => $import->cancelled_at?->toIso8601String(),
            'created_at' => $import->created_at?->toIso8601String(),
        ];
    }

    private function assertTenantOwned(Model $model, Employee $actor): void
    {
        if ($model->getAttribute('company_id') !== $actor->company_id) {
            abort(404);
        }
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
