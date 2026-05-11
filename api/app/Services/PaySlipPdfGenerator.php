<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PaySlip;
use App\Services\Payroll\CountryRules\AlgeriaPayrollRules;
use App\Services\Payroll\CountryRules\FrancePayrollRules;
use App\Services\Payroll\CountryRules\MoroccoPayrollRules;
use App\Services\Payroll\CountryRules\SenegalPayrollRules;
use App\Services\Payroll\CountryRules\TunisiaPayrollRules;
use App\Services\Payroll\CountryRules\TurkeyPayrollRules;
use Barryvdh\DomPDF\Facade\Pdf;

class PaySlipPdfGenerator
{
    private const COUNTRY_LEGAL = [
        'DZ' => 'Conformément au Code du travail algérien (Loi 90-11). CNAS employeur incluse.',
        'MA' => 'Conformément au Code du travail marocain (Loi 65-99). CNSS/AMO incluses.',
        'TN' => 'Conformément au Code du travail tunisien. CNSS incluse.',
        'FR' => 'En application du Code du travail français. Cotisations CSG/CRDS incluses. Net imposable disponible.',
        'TR' => 'İş Kanunu uyarınca düzenlenmiştir. SGK primleri dahildir.',
        'SN' => 'Conformément au Code du travail sénégalais. IPRES/CSS incluses.',
    ];

    private const COUNTRY_CURRENCY = [
        'DZ' => 'DZD',
        'MA' => 'MAD',
        'TN' => 'TND',
        'FR' => 'EUR',
        'TR' => 'TRY',
        'SN' => 'XOF',
    ];

    public function generate(PaySlip $paySlip): string
    {
        $paySlip->load(['lines', 'payrollRun', 'employee']);

        $employee = $paySlip->employee ?? Employee::find($paySlip->employee_id);
        $company = Company::find($paySlip->company_id);
        $countryCode = $paySlip->payrollRun->country_code ?? 'DZ';

        $pdf = Pdf::loadView('pdf.payslip', [
            'slip' => $paySlip,
            'lines' => $paySlip->lines->sortBy('order'),
            'employee' => $employee,
            'company' => $company,
            'currency' => self::COUNTRY_CURRENCY[$countryCode] ?? 'EUR',
            'legalMentions' => self::COUNTRY_LEGAL[$countryCode] ?? '',
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    public function generateForRun(int $payrollRunId): array
    {
        $slips = PaySlip::where('payroll_run_id', $payrollRunId)
            ->whereIn('status', ['calculated', 'validated'])
            ->get();

        $results = [];

        foreach ($slips as $slip) {
            $results[] = [
                'employee_id' => $slip->employee_id,
                'pdf' => $this->generate($slip),
            ];
        }

        return $results;
    }
}
