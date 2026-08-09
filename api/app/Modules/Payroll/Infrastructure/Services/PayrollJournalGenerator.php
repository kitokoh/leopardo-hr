<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Support\Collection;

/**
 * Programme FOCUS — F-10 : journal de paie mensuel (CSV).
 *
 * Une ligne par bulletin validé : matricule, nom, brut, cotisations
 * salariales, IRG, autres déductions, net à payer, coût employeur.
 * Une ligne de totaux clôt le fichier (contrôle comptable).
 * Rejouable et horodaté par le run (régime de preuve).
 */
class PayrollJournalGenerator
{
    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule', 'lines'])
            ->where('status', 'validated')
            ->get();

        $header = ['matricule', 'nom', 'brut', 'cotisations_salariales', 'irg', 'autres_deductions', 'net_a_payer', 'cout_employeur'];
        $rows = [$header];

        $totals = array_fill(0, count($header) - 1, 0.0);

        foreach ($slips as $slip) {
            $employeeCtas = (float) $slip->lines->where('name', 'Cotisations salariales')->sum('amount');
            $irg = (float) $slip->lines->where('name', 'Impot sur le revenu')->sum('amount');
            $otherDeductions = (float) $slip->lines
                ->where('type', 'deduction')
                ->reject(fn ($line) => in_array($line->name, ['Cotisations salariales', 'Impot sur le revenu'], true))
                ->sum('amount');
            $employerCost = (float) $slip->lines->where('type', 'employer_contribution')->sum('amount');

            $row = [
                (string) ($slip->employee->matricule ?? $slip->employee_id),
                trim(($slip->employee->first_name ?? '').' '.($slip->employee->last_name ?? '')),
                number_format((float) $slip->gross_salary, 2, '.', ''),
                number_format($employeeCtas, 2, '.', ''),
                number_format($irg, 2, '.', ''),
                number_format($otherDeductions, 2, '.', ''),
                number_format((float) $slip->net_salary, 2, '.', ''),
                number_format($employerCost, 2, '.', ''),
            ];

            $values = [(float) $slip->gross_salary, $employeeCtas, $irg, $otherDeductions, (float) $slip->net_salary, $employerCost];
            foreach ($values as $i => $value) {
                $totals[$i] += $value;
            }

            $rows[] = $row;
        }

        $rows[] = [
            'TOTAL',
            "{$slips->count()} bulletins",
            ...array_map(fn (float $t): string => number_format($t, 2, '.', ''), $totals),
        ];

        return $this->toCsv($rows);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $lines = array_map(static function (array $row): string {
            return implode(',', array_map(static function ($cell): string {
                $cell = (string) $cell;

                return '"'.str_replace('"', '""', $cell).'"';
            }, $row));
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
