<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Attendance\Infrastructure\Services\AttendanceReportService;
use App\Support\CsvCellSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Export CSV mensuel des présences — issue #5696.
 *
 * Vit dans le module Attendance (propriétaire de l'agrégation) : réutilise
 * `AttendanceReportService` (même agrégation que `/attendance/monthly-report`)
 * et expose la synthèse par employé sur la surface d'export canonique
 * `/export/*` via `api/routes/modules/dashboard.php`.
 *
 * L'historisation (`export_history`, portail manager) est écrite via
 * `DB::table()` pour préserver l'isolation de module (issue #5584) : aucun
 * import croisé vers Modules/HR.
 */
class AttendanceExportController extends Controller
{
    /**
     * Export CSV mensuel des présences (synthèse par employé) — issue #5696.
     *
     * `GET /api/v1/export/attendance/monthly?month=YYYY-MM` :
     *   - manager seulement (403 MANAGER_REQUIRED pour un employé) ;
     *   - synthèse par employé (jours/heures/HS/retards/…) via
     *     AttendanceReportService, même agrégation que le rapport mensuel ;
     *   - enveloppe JSON {format, content, filename, count, month} avec
     *     neutralisation OWASP (CsvCellSanitizer) ;
     *   - ligne export_history tracée (type attendance_monthly) ;
     *   - `month` strictement validé (YYYY-MM), défaut = mois courant.
     */
    public function attendanceMonthly(Request $request, AttendanceReportService $reportService): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        if (! $user->isManager()) {
            abort(403, 'MANAGER_REQUIRED');
        }

        $validated = $request->validate([
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = (string) ($validated['month'] ?? now()->format('Y-m'));

        /** @var Company $company */
        $company = currentCompany();

        $report = $reportService->build(
            $company,
            AttendanceReportService::PERIOD_MONTH,
            ['month' => $month],
            $user,
        );

        /** @var list<array<string, mixed>> $employees */
        $employees = $report['data']['employees'] ?? [];
        $rows = collect($employees)->map(static fn (array $row): \stdClass => (object) $row);
        $count = count($employees);
        $filename = 'attendance_monthly_'.$month.'.csv';

        $this->recordExport($request, $user, 'attendance_monthly', 'csv', $count, $filename);

        return response()->json([
            'data' => [
                'format' => 'csv',
                'content' => $this->toCsv($rows),
                'filename' => $filename,
                'count' => $count,
                'month' => $month,
            ],
        ]);
    }

    /**
     * @param  Collection<int, \stdClass>  $collection
     */
    private function toCsv(Collection $collection): string
    {
        if ($collection->isEmpty()) {
            return '';
        }

        $headers = array_keys((array) $collection->first());
        $csv = implode(',', $headers)."\n";

        foreach ($collection as $row) {
            $values = array_map(function ($v) {
                if ($v === null) {
                    return '';
                }
                // #4169 : neutralisation OWASP des préfixes de formule CSV —
                // les valeurs numériques (montants négatifs compris) restent
                // intactes (le sanitizer ne préfixe que les chaînes).
                $str = CsvCellSanitizer::neutralize($v);

                $escaped = str_replace('"', '""', $str);

                return str_contains($escaped, ',') || str_contains($escaped, '"') || str_contains($escaped, "\n")
                    ? '"'.$escaped.'"'
                    : $escaped;
            }, array_values((array) $row));
            $csv .= implode(',', $values)."\n";
        }

        return $csv;
    }

    /**
     * Historise un export (issue #2199) — append-only, tenant-scopé.
     * Un échec d'historisation ne doit JAMAIS faire échouer l'export.
     *
     * Écrit via `DB::table('export_history')` (et non le modèle
     * Modules/HR\ExportHistory) pour préserver l'isolation de module (#5584).
     */
    private function recordExport(Request $request, ?Employee $user, string $type, string $format, int $count, ?string $filename = null): void
    {
        if ($user === null) {
            return;
        }

        try {
            DB::table('export_history')->insert([
                'company_id' => $user->company_id,
                'employee_id' => $user->id,
                'type' => $type,
                'format' => $format,
                'record_count' => $count,
                'filename' => $filename,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // L'export reste fonctionnel même si la table est indisponible.
        }
    }
}
