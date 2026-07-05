<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use Closure;

class PayrollAccountingExportService
{
    /**
     * Generates a closure that streams the CSV export of a payroll run.
     * Includes UTF-8 BOM for Excel compatibility.
     */
    public function generateCsvClosure(PayrollRun $run): Closure
    {
        $slips = $run->paySlips()->with('employee')->get();

        return function () use ($slips) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fwrite($file, "\xEF\xBB\xBF");
            
            fputcsv($file, [
                'Matricule',
                'Nom',
                'Prénom',
                'Type Salaire',
                'Salaire Brut',
                'Déductions',
                'Salaire Net',
                'Coût Employeur'
            ], ';');

            foreach ($slips as $slip) {
                fputcsv($file, [
                    $slip->employee->matricule ?? '',
                    $slip->employee->last_name ?? '',
                    $slip->employee->first_name ?? '',
                    $slip->employee->salary_type ?? '',
                    number_format((float) $slip->gross_salary, 2, '.', ''),
                    number_format((float) $slip->total_deductions, 2, '.', ''),
                    number_format((float) $slip->net_salary, 2, '.', ''),
                    number_format((float) $slip->total_cost, 2, '.', '')
                ], ';');
            }

            fclose($file);
        };
    }
}
