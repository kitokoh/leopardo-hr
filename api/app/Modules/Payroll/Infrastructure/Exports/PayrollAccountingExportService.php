<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Support\CsvCellSanitizer;
use Closure;

/**
 * PA2-PAY-017 — Accounting-oriented CSV export of a payroll run's pay slips,
 * for a company's accountant/bookkeeper to import into external tools. Each
 * row carries the currency, country, and pay period alongside the amounts so
 * the file remains self-describing once downloaded (no implicit context
 * lost outside of the app).
 */
class PayrollAccountingExportService
{
    /**
     * Generates a closure that streams the CSV export of a payroll run.
     * Includes UTF-8 BOM for Excel compatibility.
     */
    public function generateCsvClosure(PayrollRun $run): Closure
    {
        // Issue #2223 : seuls les bulletins validés sont exportés (le journal
        // voisin suit la même règle) — pas de statuts intermédiaires.
        $slips = $run->paySlips()->with(['employee', 'lines'])->where('status', 'validated')->get();
        $currency = $this->resolveCurrency($run);
        $periodStart = $run->period_start->toDateString();
        $periodEnd = $run->period_end->toDateString();
        $countryCode = (string) $run->country_code;

        return function () use ($slips, $currency, $periodStart, $periodEnd, $countryCode) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fwrite($file, "\xEF\xBB\xBF");

            $header = [
                'Matricule',
                'Nom',
                'Prénom',
                'Type Salaire',
                'Pays',
                'Devise',
                'Période Début',
                'Période Fin',
                'Salaire Brut',
                'Déductions',
                'Salaire Net',
                'Coût Employeur',
            ];

            // #5243 — bridge vers le flux comptable DZ : colonnes cotisations
            // ajoutées pour les runs DZ uniquement (contrat multi-pays intact).
            $dzColumns = $countryCode === 'DZ';
            if ($dzColumns) {
                array_push($header, 'CNAS Salariale', 'CNAS Patronale', 'IRG');
            }

            fputcsv($file, $header, ';');

            foreach ($slips as $slip) {
                $row = [
                    CsvCellSanitizer::neutralize((string) ($slip->employee->matricule ?? '')),
                    CsvCellSanitizer::neutralize((string) ($slip->employee->last_name ?? '')),
                    CsvCellSanitizer::neutralize((string) ($slip->employee->first_name ?? '')),
                    CsvCellSanitizer::neutralize((string) ($slip->employee->salary_type ?? '')),
                    $countryCode,
                    $currency,
                    $periodStart,
                    $periodEnd,
                    number_format((float) $slip->gross_salary, 2, '.', ''),
                    number_format((float) $slip->total_deductions, 2, '.', ''),
                    number_format((float) $slip->net_salary, 2, '.', ''),
                    number_format((float) $slip->total_cost, 2, '.', ''),
                ];

                if ($dzColumns) {
                    array_push($row, ...$this->dzContributionColumns($slip));
                }

                fputcsv($file, $row, ';');
            }

            fclose($file);
        };
    }

    /**
     * The payroll run itself does not persist a currency column (amounts are
     * computed in the company's currency by CountryRules), so this falls
     * back to the owning company's configured currency.
     */
    private function resolveCurrency(PayrollRun $run): string
    {
        /** @var Company|null $company */
        $company = $run->relationLoaded('company')
            ? $run->getRelation('company')
            : Company::query()->where('id', $run->company_id)->first();

        return strtoupper((string) ($company?->currency ?? 'DZD'));
    }

    /**
     * #5243 — Colonnes cotisations DZ pour l'export comptable : CNAS
     * salariale (ligne « Cotisations salariales », sinon 9 % du brut), CNAS
     * patronale (lignes « employer_contribution », sinon 26 %), IRG (déductions
     * hors cotisations salariales). Les montants suivent les lignes réelles
     * du bulletin quand elles existent.
     *
     * @return list<string>
     */
    private function dzContributionColumns(PaySlip $slip): array
    {
        $gross = (float) $slip->gross_salary;

        $cnasEmployee = (float) $slip->lines
            ->where('type', 'deduction')
            ->where('name', 'Cotisations salariales')
            ->sum('amount');
        if ($cnasEmployee <= 0.0) {
            $cnasEmployee = round($gross * 0.09, 2);
        }

        $cnasEmployer = (float) $slip->lines
            ->where('type', 'employer_contribution')
            ->sum('amount');
        if ($cnasEmployer <= 0.0) {
            $cnasEmployer = round($gross * 0.26, 2);
        }

        $irg = (float) $slip->lines
            ->where('type', 'deduction')
            ->where('name', '!=', 'Cotisations salariales')
            ->sum('amount');

        return [
            number_format($cnasEmployee, 2, '.', ''),
            number_format($cnasEmployer, 2, '.', ''),
            number_format($irg, 2, '.', ''),
        ];
    }
}
