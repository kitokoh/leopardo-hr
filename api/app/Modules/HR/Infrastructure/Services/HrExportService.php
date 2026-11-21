<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\ExportHistory;
use App\Support\CsvCellSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mécanique commune des exports RH (`ExportController`).
 *
 * Extrait de `ExportController` (522 lignes) : la lecture de table, la
 * sérialisation CSV, l'historisation et les agrégations comptables
 * (journal / grand livre / OD) vivaient dans la couche HTTP. Ce sont des
 * règles métier — la ventilation 641/431/421 de l'OD est une écriture
 * comptable, pas du formatage de réponse — et elles doivent être testables
 * sans monter une requête HTTP complète.
 *
 * Le contrôleur ne garde que le contrat : RBAC, validation, choix du rapport.
 */
class HrExportService
{
    /**
     * Lit une table en ne gardant que les colonnes réellement présentes.
     *
     * Le schéma varie selon les modules activés pour la société : demander une
     * colonne absente ferait échouer la requête entière. On filtre donc sur
     * `Schema::hasColumn` et on renvoie une collection vide si aucune colonne
     * ne survit — un export vide est un résultat valide, pas une erreur.
     *
     * Un `companyId` nul signifie « aucun tenant » : on renvoie une
     * collection vide plutôt que de laisser la requête comparer à NULL (ce qui
     * ne ramènerait rien de toute façon, mais sans le dire).
     *
     * @param  list<string>  $columns
     * @return Collection<int, \stdClass>
     */
    public function tableForCompany(string $table, string|int|null $companyId, array $columns): Collection
    {
        if ($companyId === null || ! Schema::hasTable($table)) {
            return collect();
        }

        $availableColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));

        if ($availableColumns === []) {
            return collect();
        }

        $query = DB::table($table)->select($availableColumns);

        if (Schema::hasColumn($table, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        return $query->orderByDesc(Schema::hasColumn($table, 'created_at') ? 'created_at' : $availableColumns[0])
            ->limit(10000)
            ->get();
    }

    /**
     * Sérialise une collection en CSV.
     *
     * #4169 : neutralisation OWASP des préfixes de formule — les valeurs
     * numériques (montants négatifs compris) restent intactes, le sanitizer ne
     * préfixe que les chaînes.
     *
     * @param  Collection<int, \stdClass>  $collection
     */
    public function toCsv(Collection $collection): string
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
     * Réponse d'export au format demandé (json par défaut, csv/xlsx en CSV).
     *
     * Le type d'historisation (#2199) est distinct du préfixe de fichier :
     * convention établie `employees` → `employees_export_*.csv` (#5034).
     *
     * @param  Collection<int, \stdClass>  $records
     */
    public function exportResponse(Request $request, Collection $records, string $filenamePrefix, ?string $type = null): JsonResponse
    {
        $type ??= $filenamePrefix;

        $validated = $request->validate([
            'format' => 'nullable|in:json,csv,xlsx',
        ]);

        /** @var Employee|null $user */
        $user = $request->user();

        $format = $validated['format'] ?? 'json';
        if ($format === 'csv' || $format === 'xlsx') {
            $filename = $filenamePrefix.'_'.now()->format('Y-m-d').'.csv';
            $this->recordExport($request, $user, $type, 'csv', $records->count(), $filename);

            return response()->json([
                'data' => [
                    'format' => 'csv',
                    'content' => $this->toCsv($records),
                    'filename' => $filename,
                    'count' => $records->count(),
                ],
            ]);
        }

        $this->recordExport($request, $user, $type, 'json', $records->count());

        return response()->json([
            'data' => [
                'format' => 'json',
                'records' => $records,
                'count' => $records->count(),
            ],
        ]);
    }

    /**
     * Variante d'export à nom de fichier imposé (ex. période dans le nom).
     *
     * `exportResponse()` dérive le nom du préfixe et de la date du jour ; ce
     * cas-ci a besoin d'un nom porteur de la période (`attendance_export_…`),
     * mais la mécanique json/csv et l'historisation sont identiques.
     *
     * @param  Collection<int, \stdClass>  $records
     */
    public function exportWithFilename(Request $request, Collection $records, string $type, string $filename): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|in:json,csv',
        ]);

        /** @var Employee|null $user */
        $user = $request->user();

        if (($validated['format'] ?? 'json') === 'csv') {
            $this->recordExport($request, $user, $type, 'csv', $records->count(), $filename);

            return response()->json([
                'data' => [
                    'format' => 'csv',
                    'content' => $this->toCsv($records),
                    'filename' => $filename,
                    'count' => $records->count(),
                ],
            ]);
        }

        $this->recordExport($request, $user, $type, 'json', $records->count());

        return response()->json([
            'data' => [
                'format' => 'json',
                'records' => $records,
                'count' => $records->count(),
            ],
        ]);
    }

    /**
     * Historise un export (issue #2199) — append-only, tenant-scopé.
     *
     * Un échec d'historisation ne doit JAMAIS faire échouer l'export : la
     * table peut être indisponible sur un schéma ancien, et perdre une ligne
     * d'audit est moins grave que perdre le fichier demandé.
     */
    public function recordExport(Request $request, ?Employee $user, string $type, string $format, int $count, ?string $filename = null): void
    {
        if ($user === null) {
            return;
        }

        try {
            ExportHistory::create([
                // #7711 : company_id délégué au trait BelongsToCompany (tenant actif).
                'employee_id' => $user->id,
                'type' => $type,
                'format' => $format,
                'record_count' => $count,
                'filename' => $filename,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500),
            ]);
        } catch (\Throwable) {
            // L'export reste fonctionnel même si la table est indisponible.
        }
    }

    /**
     * Grand livre : totaux par run de paie.
     *
     * @param  Collection<int, \stdClass>  $records
     * @return Collection<int, \stdClass>
     */
    public function ledgerRows(Collection $records): Collection
    {
        return $records->groupBy('payroll_run_id')->map(function ($group, $runId): \stdClass {
            $row = new \stdClass;
            $row->payroll_run_id = $runId;
            $row->total_gross = $group->sum('gross_salary');
            $row->total_net = $group->sum('net_salary');
            $row->total_deductions = $group->sum('gross_salary') - $group->sum('net_salary');

            return $row;
        })->values();
    }

    /**
     * Écritures d'OD par run de paie (plan comptable SYSCOHADA).
     *
     * 641 — Rémunérations du personnel (débit)
     * 431 — Sécurité sociale (crédit)
     * 421 — Personnel, rémunérations dues (crédit)
     *
     * @param  Collection<int, \stdClass>  $records
     * @return Collection<int, \stdClass>
     */
    public function odEntries(Collection $records): Collection
    {
        $odEntries = collect();

        foreach ($records->groupBy('payroll_run_id') as $group) {
            $date = $group->first()->period_end ?? now()->toDateString();
            $totalGross = $group->sum('gross_salary');
            $totalNet = $group->sum('net_salary');
            $totalSocial = $totalGross - $totalNet;

            $odEntries->push((object) [
                'date' => $date,
                'account' => '641000',
                'label' => 'Rémunérations',
                'debit' => $totalGross,
                'credit' => 0,
            ]);

            $odEntries->push((object) [
                'date' => $date,
                'account' => '431000',
                'label' => 'Charges Sociales',
                'debit' => 0,
                'credit' => $totalSocial,
            ]);

            $odEntries->push((object) [
                'date' => $date,
                'account' => '421000',
                'label' => 'Rémunérations Dues',
                'debit' => 0,
                'credit' => $totalNet,
            ]);
        }

        return $odEntries;
    }
}
