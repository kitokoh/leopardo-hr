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

    /**
     * Generate a simplified DSN (Declaration Sociale Nominative) monthly export for France.
     *
     * DSN phase 3 simplified format: S10 (emetteur), S20 (entreprise), S21 (individu), S44 (versement).
     *
     * @param  Collection<int, array{employee_id: int, nir: string, last_name: string, first_name: string, date_naissance: string, gross_salary: float, net_salary: float, net_imposable: float, hours_worked: float, contract_type: string, start_date: string}>  $employees
     */
    public function generateDsnFr(
        string $companyName,
        string $companySiret,
        string $month,
        int $year,
        Collection $employees,
        string $emitterSiret = '',
    ): string {
        $lines = [];
        $envoi = now()->format('YmdHis');

        // S10 — Emetteur
        $lines[] = "S10.G00.00.001,'{$this->sanitizeDsn($emitterSiret ?: $companySiret)}'";
        $lines[] = "S10.G00.00.002,'Leopardo RH'";
        $lines[] = "S10.G00.00.003,'{$envoi}'";
        $lines[] = "S10.G00.00.005,'01'";
        $lines[] = "S10.G00.00.006,'11'";
        $lines[] = "S10.G00.00.008,'01'";

        // S20 — Entreprise
        $lines[] = "S20.G00.05.001,'{$this->sanitizeDsn($companySiret)}'";
        $lines[] = "S20.G00.05.002,'{$this->sanitizeDsn($companyName)}'";
        $lines[] = "S20.G00.05.003,'{$month}'";
        $lines[] = "S20.G00.05.004,'{$year}'";

        // S21 — Individus
        foreach ($employees as $emp) {
            $nir = $emp['nir'] ?? '';
            $lastName = mb_strtoupper($emp['last_name'] ?? '');
            $firstName = mb_strtoupper($emp['first_name'] ?? '');
            $dateNaissance = $emp['date_naissance'] ?? '';
            $contractType = $this->mapContractTypeDsn($emp['contract_type'] ?? 'CDI');
            $startDate = $emp['start_date'] ?? '';
            $gross = (float) ($emp['gross_salary'] ?? 0);
            $netImposable = (float) ($emp['net_imposable'] ?? $emp['net_salary'] ?? 0);
            $hours = (float) ($emp['hours_worked'] ?? 151.67);
            $netSalary = (float) ($emp['net_salary'] ?? 0);

            $nirClean = $this->sanitizeDsn($nir);
            $lastNameClean = $this->sanitizeDsn($lastName);
            $firstNameClean = $this->sanitizeDsn($firstName);

            $lines[] = "S21.G00.30.001,'{$nirClean}'";
            $lines[] = "S21.G00.30.002,'{$lastNameClean}'";
            $lines[] = "S21.G00.30.004,'{$firstNameClean}'";
            $lines[] = "S21.G00.30.006,'{$dateNaissance}'";

            // S21.G00.40 — Contrat
            $lines[] = "S21.G00.40.007,'{$contractType}'";
            $lines[] = "S21.G00.40.001,'{$startDate}'";

            // S21.G00.51 — Remuneration
            $grossFmt = number_format($gross, 2, '.', '');
            $netImposableFmt = number_format($netImposable, 2, '.', '');
            $hoursFmt = number_format($hours, 2, '.', '');
            $netSalaryFmt = number_format($netSalary, 2, '.', '');
            $payDate = now()->format('d/m/Y');

            $lines[] = "S21.G00.51.001,'001'";
            $lines[] = "S21.G00.51.002,'{$grossFmt}'";
            $lines[] = "S21.G00.51.011,'{$netImposableFmt}'";
            $lines[] = "S21.G00.51.012,'{$hoursFmt}'";

            // S21.G00.54 — Versement individu
            $lines[] = "S21.G00.54.001,'{$payDate}'";
            $lines[] = "S21.G00.54.002,'{$netSalaryFmt}'";
        }

        // S44 — Aggregats
        $totalGross = $employees->sum('gross_salary');
        $totalNet = $employees->sum('net_salary');

        $lines[] = "S44.G00.00.001,'".number_format(round($totalGross, 2), 2, '.', '')."'";
        $lines[] = "S44.G00.00.002,'{$employees->count()}'";
        $lines[] = "S44.G00.00.003,'".number_format(round($totalNet, 2), 2, '.', '')."'";

        return implode("\r\n", $lines)."\r\n";
    }

    private function mapContractTypeDsn(string $contractType): string
    {
        return match (mb_strtoupper($contractType)) {
            'CDI' => '01',
            'CDD' => '02',
            'INTERIM' => '03',
            'APPRENTISSAGE' => '04',
            'PROFESSIONNALISATION' => '05',
            'STAGE' => '07',
            default => '01',
        };
    }

    private function sanitizeDsn(string $value): string
    {
        return str_replace(["'", "\r", "\n"], ['', '', ''], $value);
    }

    private function sanitize(string $value): string
    {
        return str_replace(['|', ';', "\r", "\n"], ['', '', '', ''], $value);
    }
}
