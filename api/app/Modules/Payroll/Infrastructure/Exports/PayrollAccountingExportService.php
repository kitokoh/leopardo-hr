<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\PayrollCountryChartOfAccounts;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
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
                ], ';');
            }

            fclose($file);
        };
    }

    /**
     * #5256 — Écritures salariales du run (partie double), prêtes à être
     * consommées par le module Accounting (Phase C, #5239).
     *
     * Lecture seule sur les bulletins VALIDÉS (règle #2223, cohérente avec
     * l'export CSV) : pour chaque bulletin, les écritures suivent le modèle
     * COMPTABILITE_CONCEPTION.md §6.3 avec le plan comptable du pays :
     *   D  salary_expense       = salaire brut
     *   D  employer_charges     = cotisations patronales
     *   C  net_payable          = net à payer
     *   C  social_contributions = cotisations salariales + patronales
     *   C  income_tax_withheld  = impôt retenu (IRG/IRPP/ITS/PAS… + taxe forfaitaire)
     *   C  other_deductions     = reste (avances, retenues personnalisées), si > 0
     *
     * L'équilibre débit = crédit est garanti PAR CONSTRUCTION : la
     * décomposition des déductions (sociale / impôt / autres) reprend les
     * lignes du bulletin telles que produites par le moteur, et le résidu
     * est crédité sur « autres déductions ». Chaque ligne porte la
     * référence du run pour la traçabilité (audit trail).
     *
     * @return list<array{
     *     date: string,
     *     company_id: string,
     *     payroll_run_id: int,
     *     pay_slip_id: int,
     *     employee_id: int|null,
     *     account_code: string,
     *     account_label: string,
     *     debit: float,
     *     credit: float,
     *     reference: string,
     * }>
     */
    public function journalLines(PayrollRun $run): array
    {
        $chart = PayrollCountryChartOfAccounts::forCountry($run->country_code);
        if ($chart === null) {
            return [];
        }

        $accounts = $chart['accounts'];
        $slips = $run->paySlips()->with(['employee', 'lines'])->where('status', 'validated')->get();
        $date = $run->period_end->toDateString();
        $reference = sprintf('PAYROLL-RUN-%d', $run->id);

        $lines = [];
        foreach ($slips as $slip) {
            $deductions = $this->decomposeDeductions($slip, $run);

            $lines[] = $this->entry(
                $date, $run, $slip, $accounts['salary_expense'], $reference,
                debit: round((float) $slip->gross_salary, 2),
            );

            $employerCharges = round((float) $slip->employer_contributions, 2);
            if ($employerCharges != 0.0) {
                $lines[] = $this->entry(
                    $date, $run, $slip, $accounts['employer_charges'], $reference,
                    debit: $employerCharges,
                );
            }

            $lines[] = $this->entry(
                $date, $run, $slip, $accounts['net_payable'], $reference,
                credit: round((float) $slip->net_salary, 2),
            );

            $social = round($deductions['social'] + $employerCharges, 2);
            if ($social != 0.0) {
                $lines[] = $this->entry(
                    $date, $run, $slip, $accounts['social_contributions'], $reference,
                    credit: $social,
                );
            }

            $tax = round($deductions['tax'], 2);
            if ($tax != 0.0) {
                $lines[] = $this->entry(
                    $date, $run, $slip, $accounts['income_tax_withheld'], $reference,
                    credit: $tax,
                );
            }

            $other = round($deductions['other'], 2);
            if ($other != 0.0) {
                $lines[] = $this->entry(
                    $date, $run, $slip, $accounts['other_deductions'], $reference,
                    credit: $other,
                );
            }
        }

        return $lines;
    }

    /**
     * Décomposition déterministe des déductions d'un bulletin en part
     * sociale, impôt et « autres » — basée sur les lignes produites par le
     * moteur (PayrollCalculator), pas sur des devinettes de libellés :
     *   - sociale : lignes « Cotisations salariales » ;
     *   - impôt   : lignes « Impot sur le revenu » + taxe forfaitaire du
     *     pays (flatPayrollTaxLabel) quand la règle est résolvable ;
     *   - autres  : résidu = total_deductions − sociale − impôt (avances,
     *     retenues personnalisées…). L'équilibre est donc garanti même si
     *     une ligne inconnue apparaît (régularisations, nouveaux pays).
     *
     * @return array{social: float, tax: float, other: float}
     */
    private function decomposeDeductions(PaySlip $slip, PayrollRun $run): array
    {
        $social = 0.0;
        $tax = 0.0;

        $flatTaxLabel = '';
        try {
            $flatTaxLabel = (new CountryRulesResolver)->resolve((string) $run->country_code)->flatPayrollTaxLabel();
        } catch (\Throwable) {
            // Pays sans règles moteur (GB/US/CA…) : la taxe forfaitaire
            // éventuelle tombe dans le résidu « autres » (équilibre conservé).
        }

        foreach ($slip->lines as $line) {
            if ($line->type !== 'deduction') {
                continue;
            }

            $amount = (float) $line->amount;

            if ($line->name === 'Cotisations salariales') {
                $social += $amount;

                continue;
            }

            if ($line->name === 'Impot sur le revenu'
                || ($flatTaxLabel !== '' && $line->name === $flatTaxLabel)) {
                $tax += $amount;
            }
        }

        $other = max(0.0, round((float) $slip->total_deductions - $social - $tax, 2));

        return [
            'social' => $social,
            'tax' => $tax,
            'other' => $other,
        ];
    }

    /**
     * Construit une ligne d'écriture (débit OU crédit exclusif, jamais les
     * deux — montants arrondis à 2 décimales).
     *
     * @param  array{code: string, label: string}  $account
     * @return array{
     *     date: string,
     *     company_id: string,
     *     payroll_run_id: int,
     *     pay_slip_id: int,
     *     employee_id: int|null,
     *     account_code: string,
     *     account_label: string,
     *     debit: float,
     *     credit: float,
     *     reference: string,
     * }
     */
    private function entry(
        string $date,
        PayrollRun $run,
        PaySlip $slip,
        array $account,
        string $reference,
        float $debit = 0.0,
        float $credit = 0.0,
    ): array {
        return [
            'date' => $date,
            'company_id' => (string) $run->company_id,
            'payroll_run_id' => (int) $run->id,
            'pay_slip_id' => (int) $slip->id,
            'employee_id' => $slip->employee_id,
            'account_code' => $account['code'],
            'account_label' => $account['label'],
            'debit' => $debit,
            'credit' => $credit,
            'reference' => $reference,
        ];
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
