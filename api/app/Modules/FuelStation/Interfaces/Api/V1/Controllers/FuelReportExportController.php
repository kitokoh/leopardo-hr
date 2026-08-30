<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Domain\Exceptions\FuelSolutionInactiveException;
use App\Modules\FuelStation\Infrastructure\Services\FuelReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FUEL-018 (#5812) — Export CSV idempotent + URL signée.
 */
class FuelReportExportController extends Controller
{
    public function __construct(private readonly FuelReportExportService $exports)
    {
    }

    public function export(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();

        $request->validate([
            'type' => ['required', 'string', 'in:sales,shifts,cash-sessions'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'station_id' => ['nullable', 'integer'],
        ]);

        $from = $request->input('from') !== null
            ? Carbon::parse((string) $request->input('from'))->startOfDay()
            : Carbon::today();
        $to = $request->input('to') !== null
            ? Carbon::parse((string) $request->input('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        try {
            $result = $this->exports->export(
                $actor,
                (string) $request->input('type'),
                $from,
                $to,
                $request->input('station_id') !== null ? (int) $request->input('station_id') : null,
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Route signée (middleware `signed`).
     */
    public function download(Request $request, string $export): StreamedResponse|JsonResponse
    {
        return $this->exports->download($export);
    }

    private function assertSolutionActive(): void
    {
        if (! FeatureFlag::enabled('fuel_station', currentCompany())) {
            throw new FuelSolutionInactiveException;
        }
    }
}
