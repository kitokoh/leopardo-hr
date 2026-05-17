<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;

class SocialDeclarationGenerator
{
    /**
     * Generate a CNAS (Caisse Nationale des Assurances Sociales) quarterly declaration for Algeria.
     *
     * @param  Collection<int, array{employee_id: int, num_ss: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, months_worked: int}>  $employees
     */
    public function generateCnasDz(
        string $companyName,
        string $companyNis,
        string $quarter,
        int $year,
        Collection $employees,
    ): string {
        $lines = [];

        $lines[] = implode('|', [
            'ENTETE',
            $this->sanitize($companyName),
            $this->sanitize($companyNis),
            $quarter,
            (string) $year,
            now()->format('d/m/Y'),
            (string) $employees->count(),
        ]);

        $totalGross = 0.0;
        $totalContribEmployee = 0.0;
        $totalContribEmployer = 0.0;
        $seq = 1;

        foreach ($employees as $emp) {
            $gross = round($emp['gross_salary'], 2);
            $contribEmployee = round($gross * 0.09, 2);
            $contribEmployer = round($gross * 0.26, 2);

            $totalGross += $gross;
            $totalContribEmployee += $contribEmployee;
            $totalContribEmployer += $contribEmployer;

            $lines[] = implode('|', [
                'LIGNE',
                str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                $this->sanitize($emp['num_ss'] ?? ''),
                mb_strtoupper($this->sanitize($emp['last_name'] ?? '')),
                mb_strtoupper($this->sanitize($emp['first_name'] ?? '')),
                $emp['date_naissance'] ?? '',
                number_format($gross, 2, '.', ''),
                (string) ($emp['months_worked'] ?? 3),
                number_format($contribEmployee, 2, '.', ''),
                number_format($contribEmployer, 2, '.', ''),
            ]);
            $seq++;
        }

        $lines[] = implode('|', [
            'TOTAL',
            (string) $employees->count(),
            number_format(round($totalGross, 2), 2, '.', ''),
            number_format(round($totalContribEmployee, 2), 2, '.', ''),
            number_format(round($totalContribEmployer, 2), 2, '.', ''),
        ]);

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Generate a CNSS (Caisse Nationale de Securite Sociale) quarterly declaration for Morocco.
     *
     * @param  Collection<int, array{employee_id: int, num_cnss: string, last_name: string, first_name: string, cin: string, gross_salary: float, days_worked: int}>  $employees
     */
    public function generateCnssMa(
        string $companyName,
        string $companyAffiliate,
        string $quarter,
        int $year,
        Collection $employees,
    ): string {
        $lines = [];

        $lines[] = implode(';', [
            'ENTETE',
            $this->sanitize($companyName),
            $this->sanitize($companyAffiliate),
            $quarter,
            (string) $year,
            now()->format('d/m/Y'),
            (string) $employees->count(),
        ]);

        $totalGross = 0.0;
        $totalDays = 0;
        $seq = 1;

        foreach ($employees as $emp) {
            $gross = round($emp['gross_salary'], 2);
            $days = $emp['days_worked'] ?? 78;

            $totalGross += $gross;
            $totalDays += $days;

            $lines[] = implode(';', [
                'SALARIE',
                str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                $this->sanitize($emp['num_cnss'] ?? ''),
                mb_strtoupper($this->sanitize($emp['last_name'] ?? '')),
                mb_strtoupper($this->sanitize($emp['first_name'] ?? '')),
                $this->sanitize($emp['cin'] ?? ''),
                number_format($gross, 2, '.', ''),
                (string) $days,
            ]);
            $seq++;
        }

        $lines[] = implode(';', [
            'TOTAL',
            (string) $employees->count(),
            number_format(round($totalGross, 2), 2, '.', ''),
            (string) $totalDays,
        ]);

        return implode("\r\n", $lines)."\r\n";
    }

    private function sanitize(string $value): string
    {
        return str_replace(['|', ';', "\r", "\n"], ['', '', '', ''], $value);
    }
}
