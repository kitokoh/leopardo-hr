<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rapports RH cross-tenant pour le super-admin (contrat SPA admin,
 * issue #1764 : GET /v1/hr-reports — écran « Exports » du SPA).
 *
 * Types supportés (alignés sur le select du SPA) :
 *   headcount, turnover, absenteeism, payroll_summary, training_progress.
 *
 * Réponse : { data: { columns: string[], rows: array<string, mixed>[] } } —
 * le SPA rend `columns` comme en-têtes et `rows[col]` comme cellules.
 */
class PlatformHrReportController extends Controller
{
    private const TENANT_SCHEMA = 'shared_tenants';

    /** @var array<string, string> */
    private const TYPES = [
        'headcount' => 'headcount',
        'turnover' => 'turnover',
        'absenteeism' => 'absenteeism',
        'payroll_summary' => 'payroll_summary',
        'training_progress' => 'training_progress',
    ];

    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $report = match ($validated['type']) {
            'headcount' => $this->headcount(),
            'turnover' => $this->turnover($validated['start_date'], $validated['end_date']),
            'absenteeism' => $this->absenteeism($validated['start_date'], $validated['end_date']),
            'payroll_summary' => $this->payrollSummary($validated['start_date'], $validated['end_date']),
            'training_progress' => $this->trainingProgress($validated['start_date'], $validated['end_date']),
            default => ['columns' => [], 'rows' => []],
        };

        return response()->json(['data' => $report]);
    }

    /** @return array{columns: string[], rows: array<int, array<string, mixed>>} */
    private function headcount(): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.employees')
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row): array => ['Statut' => $row->status, 'Effectif' => (int) $row->total])
                ->values()
                ->all();

            $total = array_sum(array_column($rows, 'Effectif'));
            $rows[] = ['Statut' => 'TOTAL', 'Effectif' => $total];

            return ['columns' => ['Statut', 'Effectif'], 'rows' => $rows];
        } catch (\Throwable) {
            return ['columns' => ['Statut', 'Effectif'], 'rows' => []];
        }
    }

    /** @return array{columns: string[], rows: array<int, array<string, mixed>>} */
    private function turnover(string $start, string $end): array
    {
        try {
            $hires = DB::table(self::TENANT_SCHEMA.'.employees')
                ->select(DB::raw("to_char(hire_date, 'YYYY-MM') as month"), DB::raw('count(*) as hires'))
                ->whereBetween('hire_date', [$start, $end])
                ->groupBy(DB::raw("to_char(hire_date, 'YYYY-MM')"))
                ->pluck('hires', 'month');

            $departures = DB::table(self::TENANT_SCHEMA.'.contracts')
                ->select(DB::raw("to_char(end_date, 'YYYY-MM') as month"), DB::raw('count(*) as departures'))
                ->whereBetween('end_date', [$start, $end])
                ->groupBy(DB::raw("to_char(end_date, 'YYYY-MM')"))
                ->pluck('departures', 'month');

            $months = array_unique(array_merge($hires->keys()->all(), $departures->keys()->all()));
            sort($months);

            $rows = [];
            $running = 0;
            foreach ($months as $month) {
                $h = (int) ($hires[$month] ?? 0);
                $d = (int) ($departures[$month] ?? 0);
                $running += $h - $d;
                $rows[] = ['Mois' => $month, 'Embauches' => $h, 'Departs' => $d, 'Effectif net' => $running];
            }

            return ['columns' => ['Mois', 'Embauches', 'Departs', 'Effectif net'], 'rows' => $rows];
        } catch (\Throwable) {
            return ['columns' => ['Mois', 'Embauches', 'Departs', 'Effectif net'], 'rows' => []];
        }
    }

    /** @return array{columns: string[], rows: array<int, array<string, mixed>>} */
    private function absenteeism(string $start, string $end): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.absences as a')
                ->leftJoin(self::TENANT_SCHEMA.'.absence_types as t', 't.id', '=', 'a.absence_type_id')
                ->select('t.name as type', DB::raw('sum(a.days_count) as days'))
                ->where('a.status', 'approved')
                ->where('a.start_date', '<=', $end)
                ->where('a.end_date', '>=', $start)
                ->groupBy('t.name')
                ->orderByDesc('days')
                ->get()
                ->map(fn ($row): array => ['Type' => $row->type ?? 'Autre', 'Jours' => (float) $row->days])
                ->values()
                ->all();

            return ['columns' => ['Type', 'Jours'], 'rows' => $rows];
        } catch (\Throwable) {
            return ['columns' => ['Type', 'Jours'], 'rows' => []];
        }
    }

    /** @return array{columns: string[], rows: array<int, array<string, mixed>>} */
    private function payrollSummary(string $start, string $end): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.pay_slips')
                ->select(
                    DB::raw("to_char(period_start, 'YYYY-MM') as month"),
                    DB::raw('count(*) as bulletins'),
                    DB::raw('round(sum(gross_salary), 2) as brut'),
                    DB::raw('round(sum(net_salary), 2) as net')
                )
                ->whereBetween('period_start', [$start, $end])
                ->groupBy(DB::raw("to_char(period_start, 'YYYY-MM')"))
                ->orderBy('month')
                ->get()
                ->map(fn ($row): array => [
                    'Mois' => $row->month,
                    'Bulletins' => (int) $row->bulletins,
                    'Brut' => (float) $row->brut,
                    'Net' => (float) $row->net,
                ])
                ->all();

            return ['columns' => ['Mois', 'Bulletins', 'Brut', 'Net'], 'rows' => $rows];
        } catch (\Throwable) {
            return ['columns' => ['Mois', 'Bulletins', 'Brut', 'Net'], 'rows' => []];
        }
    }

    /** @return array{columns: string[], rows: array<int, array<string, mixed>>} */
    private function trainingProgress(string $start, string $end): array
    {
        try {
            $rows = DB::table(self::TENANT_SCHEMA.'.training_enrollments as e')
                ->leftJoin(self::TENANT_SCHEMA.'.training_courses as c', 'c.id', '=', 'e.course_id')
                ->select(
                    'c.title as formation',
                    DB::raw('count(*) as inscrits'),
                    DB::raw("count(*) filter (where e.status = 'completed') as completes")
                )
                ->whereBetween('e.enrolled_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->groupBy('c.title')
                ->orderByDesc('inscrits')
                ->get()
                ->map(fn ($row): array => [
                    'Formation' => $row->formation ?? 'Inconnue',
                    'Inscrits' => (int) $row->inscrits,
                    'Completes' => (int) $row->completes,
                ])
                ->all();

            return ['columns' => ['Formation', 'Inscrits', 'Completes'], 'rows' => $rows];
        } catch (\Throwable) {
            return ['columns' => ['Formation', 'Inscrits', 'Completes'], 'rows' => []];
        }
    }
}
