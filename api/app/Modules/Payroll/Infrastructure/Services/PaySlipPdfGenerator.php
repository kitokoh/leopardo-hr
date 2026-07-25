<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Support\CountryDefaults;
use App\Support\I18nCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;

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

    public function generate(PaySlip $paySlip): string
    {
        $paySlip->load(['lines', 'payrollRun', 'employee']);

        $employee = $paySlip->employee ?? Employee::find($paySlip->employee_id);
        $company = Company::find($paySlip->company_id);
        $countryCode = $paySlip->payrollRun->country_code ?? 'DZ';

        // PDF jobs run outside the HTTP request lifecycle, so the SetLocale
        // middleware never runs here — the locale must be applied explicitly
        // before rendering, following the same priority as the API middleware.
        App::setLocale(I18nCatalog::normalizeLocale(
            $employee?->preferred_language ?? $company?->language
        ));

        $pdf = Pdf::loadView('pdf.payslip', [
            'slip' => $paySlip,
            'lines' => $paySlip->lines->sortBy('order'),
            'employee' => $employee,
            'company' => $company,
            // PA2-I18N-003: resolve the pay slip currency from the payroll
            // run's own country through the canonical CountryDefaults
            // catalogue, instead of this class's own partial hardcoded map
            // (which only covered 6 of the ~20 countries CountryDefaults
            // already supports, e.g. CEMAC/CEDEAO members, GB, US, CA).
            'currency' => CountryDefaults::for($countryCode)['currency'],
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
