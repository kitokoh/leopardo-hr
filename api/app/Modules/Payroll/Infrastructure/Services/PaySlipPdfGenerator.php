<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Support\CountryDefaults;
use App\Support\I18nCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;

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
            $employee->preferred_language ?? $company?->language
        ));

        // Issue #5242 — police arabe (Almarai, OFL) : dompdf n'embarque que
        // les polices enregistrées ; sans elle, le rendu RTL retombe sur
        // DejaVu Sans dont la couverture arabe est partielle (cassure).
        $this->ensureArabicFonts();

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
            // F-09 (#1539) : mentions légales DZ (NIF, RC, n° CNAS employeur,
            // ID.Nat) portées par company.metadata — affichées si présentes.
            'companyLegal' => $this->companyLegalIdentifiers($company),
            // F-09 (#1539) : cumuls annuels (brut, retenues, net) des
            // bulletins validés de l'employé jusqu'à la période du bulletin.
            'annualCumuls' => $this->annualCumuls($paySlip),
            // DZ-DEPTH (#1818) : mention « BULLETIN DE RÉGULARISATION » en
            // tête de bulletin quand le run est une régularisation.
            'isRegularization' => $paySlip->payrollRun?->type === PayrollRun::TYPE_REGULARIZATION,
            'originalRunId' => $paySlip->payrollRun?->original_run_id,
            // Issue #1983 : référence au bulletin ORIGINAL corrigé (plus
            // précise que le run quand le delta est par bulletin).
            'originalSlipId' => $paySlip->original_slip_id,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * F-09 (#1539) — identifiants légaux de l'employeur (DZ) portés par
     * `company.metadata` : legal_nif, legal_rc, legal_cnas_employer,
     * legal_idnat. Retourne uniquement les clés présentes.
     *
     * @return array<string, string>
     */
    private function companyLegalIdentifiers(?Company $company): array
    {
        if ($company === null) {
            return [];
        }

        $keys = [
            'legal_nif' => 'NIF',
            'legal_rc' => 'RC',
            'legal_cnas_employer' => 'N° CNAS employeur',
            'legal_idnat' => 'ID.Nat',
        ];

        $out = [];
        foreach ($keys as $key => $label) {
            $value = $company->metadata[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$label] = $value;
            }
        }

        return $out;
    }

    /**
     * F-09 (#1539) — cumuls annuels de l'employé (brut, retenues, net) sur
     * les bulletins validés de l'année de la période du bulletin courant.
     *
     * @return array{gross: float, deductions: float, net: float}
     */
    private function annualCumuls(PaySlip $paySlip): array
    {
        $year = $paySlip->period_start->format('Y');

        $aggregates = PaySlip::query()
            ->where('company_id', $paySlip->company_id)
            ->where('employee_id', $paySlip->employee_id)
            ->where('status', 'validated')
            ->whereYear('period_start', $year)
            ->where('period_end', '<=', $paySlip->period_end)
            ->selectRaw('COALESCE(SUM(gross_salary), 0) as gross')
            ->selectRaw('COALESCE(SUM(total_deductions), 0) as deductions')
            ->selectRaw('COALESCE(SUM(net_salary), 0) as net')
            ->first();

        if ($aggregates === null) {
            return ['gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
        }

        $gross = $aggregates->getAttribute('gross');
        $deductions = $aggregates->getAttribute('deductions');
        $net = $aggregates->getAttribute('net');

        return [
            'gross' => is_numeric($gross) ? (float) $gross : 0.0,
            'deductions' => is_numeric($deductions) ? (float) $deductions : 0.0,
            'net' => is_numeric($net) ? (float) $net : 0.0,
        ];
    }

    /**
     * @return list<array{employee_id: int|null, pdf: string}>
     */
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

    /**
     * Issue #5242 — enregistre la police arabe Almarai (OFL) auprès de dompdf.
     * Les TTFs vivent dans `api/resources/fonts/` (committés) ; les métriques
     * dompdf sont cachées dans `storage/fonts` (runtime, gitignoré).
     * L'enregistrement est idempotent par processus.
     */
    private function ensureArabicFonts(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $fontDir = resource_path('fonts');
        $regular = $fontDir.'/Almarai-Regular.ttf';
        $bold = $fontDir.'/Almarai-Bold.ttf';

        if (! is_file($regular) || ! is_file($bold)) {
            return; // fonts absentes (dev partiel) : fallback DejaVu, pas de crash.
        }

        $dompdf = app('dompdf');
        $fontMetrics = $dompdf->getFontMetrics();

        // Cache des métriques dompdf (storage/fonts, runtime — gitignoré).
        File::ensureDirectoryExists(storage_path('fonts'));

        $fontMetrics->registerFont([
            'family' => 'Almarai',
            'style' => 'normal',
            'weight' => 'normal',
        ], $regular);
        $fontMetrics->registerFont([
            'family' => 'Almarai',
            'style' => 'normal',
            'weight' => 'bold',
        ], $bold);

        $registered = true;
    }
}
