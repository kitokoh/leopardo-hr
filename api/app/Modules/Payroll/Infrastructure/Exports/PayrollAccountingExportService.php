<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
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
        // Issue #2223 : seuls les bulletins VALIDÉS sont exportés (un
        // brouillon/calculé ne doit pas entrer dans la compta).
        $slips = $run->paySlips()->with('employee')->where('status', 'validated')->get();
        $currency = $this->resolveCurrency($run);
        $periodStart = $run->period_start->toDateString();
        $periodEnd = $run->period_end->toDateString();
        $countryCode = (string) $run->country_code;

        return function () use ($slips, $currency, $periodStart, $periodEnd, $countryCode) {
            $file = fopen('php://output', 'w');

            // Add BOM for Excel UTF-8 compatibility
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
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
            ], ';');

            foreach ($slips as $slip) {
                fputcsv($file, [
                    // Issue #2223 : neutralisation CSV des champs TEXTE
                    // contrôlés par l'employé (=CMD() interdit) — les
                    // montants restent des nombres.
                    $this->csvSafe((string) ($slip->employee->matricule ?? '')),
                    $this->csvSafe((string) ($slip->employee->last_name ?? '')),
                    $this->csvSafe((string) ($slip->employee->first_name ?? '')),
                    $this->csvSafe((string) ($slip->employee->salary_type ?? '')),
                    $countryCode,
                    $currency,
                    $periodStart,
                    $periodEnd,
                    number_format((float) $slip->gross_salary, 2, '.', ''),
                    number_format((float) $slip->total_deductions, 2, '.', ''),
                    number_format((float) $slip->net_salary, 2, '.', ''),
                    number_format((float) $slip->total_cost, 2, '.', ''),
                ], ';');
            }

            fclose($file);
        };
    }

    /**
     * Issue #2223 — neutralisation CSV formula injection (OWASP) sur les
     * champs texte : une valeur commençant par =, +, -, @, tab ou CR est
     * préfixée d'une apostrophe (les montants numériques ne passent pas ici).
     */
    private function csvSafe(string $value): string
    {
        if ($value !== '' && str_contains('=+-@'."\t".chr(13), $value[0])) {
            return "'".$value;
        }

        return $value;
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
}
