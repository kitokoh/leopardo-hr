<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EstimationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * MeController — endpoints self-service pour l'employe connecte.
 *
 * Ces routes existent pour permettre a un employe (role=employee ou manager
 * avec n'importe quel sous-role) de consulter ses propres heures, heures
 * supplementaires et du estime sans avoir besoin de connaitre son id.
 *
 * Les controllers /employees/{id}/* restent reserves aux managers via la
 * policy viewAny sur le modele Employee.
 */
class MeController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService) {}

    public function dailySummary(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $summary = $this->estimationService->dailySummary(
            employee: $employee,
            date: $request->input('date'),
        );

        return new JsonResponse(['data' => $summary]);
    }

    public function quickEstimate(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $company = app('current_company');
        $today = now('UTC')->setTimezone($company->timezone)->startOfDay();
        $defaultFrom = $today->copy()->startOfMonth()->toDateString();
        $defaultTo = $today->toDateString();

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $validated['from'] ?? $defaultFrom,
            to: $validated['to'] ?? $defaultTo,
        );

        return new JsonResponse(['data' => $estimate]);
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $company = app('current_company');
        $today = now('UTC')->setTimezone($company->timezone)->startOfDay();

        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = $validated['year'] ?? (int) $today->format('Y');
        $month = $validated['month'] ?? (int) $today->format('m');

        $from = Carbon::create($year, $month, 1, 0, 0, 0, $company->timezone)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $from->toDateString(),
            to: $to->toDateString(),
        );

        return new JsonResponse([
            'data' => array_merge($estimate, [
                'year' => $year,
                'month' => $month,
            ]),
        ]);
    }
}
