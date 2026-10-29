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
        'GB' => 'Issued in accordance with UK employment law. PAYE and National Insurance Class 1 included. Statutory minimum notice under the Employment Rights Act 1996.',
        'US' => 'Issued in accordance with US federal payroll rules. FICA (Social Security and Medicare), FUTA and federal income tax withholding included. FLSA overtime rules apply.',
        'CA' => 'Issued under the Canada Labour Code / Émis conformément au Code canadien du travail. CPP/RPC, CPP2 and EI/AE included.',
        // ── CEMAC / CEDEAO (#9045) — référentiels pilot, voir
        // docs/payroll/BULLETIN_{CM,CI}_MENTIONS.md. La formulation reste
        // descriptive du régime appliqué, jamais une promesse de conformité
        // (règle anti-over-promise #7922).
        'CM' => 'Établi conformément au Code du travail camerounais loi n° 92/007, art. 68. Retenues CNPS (PVID/CAC/CFC) et IRPP incluses.',
        'GA' => 'Établi conformément au Code du travail gabonais. Cotisations CNSS et IRPP incluses.',
        'CG' => 'Établi conformément au Code du travail congolais. Cotisations CNSS et IRPP incluses.',
        'TD' => 'Établi conformément au Code du travail tchadien. Cotisations CNPS et IRPP incluses.',
        'CF' => 'Établi conformément au Code du travail centrafricain. Cotisations CNSS et IRPP incluses.',
        'GQ' => 'Établi conformément au Code du travail équato-guinéen. Cotisations INSESO et IRPP incluses.',
        'CI' => 'Établi conformément au Code du travail ivoirien loi n° 2015-532, art. 32.5. ITS et CNPS incluses.',
        'BF' => 'Établi conformément au Code du travail burkinabè. Cotisations CNSS et IUTS incluses.',
        'ML' => 'Établi conformément au Code du travail malien. Cotisations INPS et ITS incluses.',
        'TG' => 'Établi conformément au Code du travail togolais. Cotisations CNSS et IRPP incluses.',
        'BJ' => 'Établi conformément au Code du travail béninois. Cotisations CNSS et ITS incluses.',
        'NE' => 'Établi conformément au Code du travail nigérien. Cotisations CNSS et IUTS incluses.',
    ];

    /**
     * Identifiants légaux employeur à afficher, par pays (#9045) : le jeu de
     * clés `company.metadata` attendu suit le référentiel de chaque juridiction
     * (RCCM/NIU en CEMAC, RCCM/IFU ou NIF en CEDEAO, SIREN/SIRET en FR, VKN en
     * TR, BN/TPS en CA). La source documentaire par pays est
     * `docs/payroll/BULLETIN_{...}_MENTIONS.md`.
     *
     * Un pays absent de cette table conserve le comportement historique
     * (jeu DZ), ce qui garantit la non-régression du bulletin algérien.
     *
     * @var array<string, array<string, string>>
     */
    private const COUNTRY_LEGAL_IDENTIFIERS = [
        'CM' => ['legal_rccm' => 'RCCM', 'legal_niu' => 'NIU', 'legal_cnps_employer' => 'N° CNPS employeur'],
        'GA' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'CG' => ['legal_rccm' => 'RCCM', 'legal_niu' => 'NIU', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'TD' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_cnps_employer' => 'N° CNPS employeur'],
        'CF' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'GQ' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_inseso_employer' => 'N° INSESO employeur'],
        'CI' => ['legal_rccm' => 'RCCM', 'legal_ncc' => 'NCC DGI', 'legal_cnps_employer' => 'N° CNPS employeur'],
        'BF' => ['legal_rccm' => 'RCCM', 'legal_ifu' => 'IFU', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'ML' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_inps_employer' => 'N° INPS employeur'],
        'TG' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'BJ' => ['legal_rccm' => 'RCCM', 'legal_ifu' => 'IFU', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'NE' => ['legal_rccm' => 'RCCM', 'legal_nif' => 'NIF', 'legal_cnss_employer' => 'N° CNSS employeur'],
        'FR' => ['legal_siren' => 'SIREN', 'legal_siret' => 'SIRET', 'legal_tva_intra' => 'TVA intracommunautaire'],
        'TR' => ['legal_vkn' => 'Vergi No (VKN)', 'legal_sicil' => 'Ticaret Sicil No'],
        'CA' => ['legal_bn' => 'BN', 'legal_gst_hst' => 'N° TPS/TVH', 'legal_tvq' => 'N° TVQ'],
    ];

    /** Jeu historique DZ (F-09, #1539) — conservé comme défaut. */
    private const DEFAULT_LEGAL_IDENTIFIERS = [
        'legal_nif' => 'NIF',
        'legal_rc' => 'RC',
        'legal_cnas_employer' => 'N° CNAS employeur',
        'legal_idnat' => 'ID.Nat',
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
            // F-09 (#1539) : mentions légales employeur (NIF, RC, n° CNAS…) portées
            // par company.metadata — jeu d'identifiants résolu par pays (#9045).
            'companyLegal' => $this->companyLegalIdentifiers($company, $countryCode),
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
     * Mentions légales de l'employeur portées par `company.metadata`.
     *
     * #9045 : le jeu d'identifiants attendu dépend de la juridiction (RCCM/NIU
     * en CEMAC, RCCM/IFU en CEDEAO, SIREN en FR, VKN en TR, BN/TPS en CA).
     * Un pays non listé retombe sur le jeu DZ historique (F-09, #1539), ce qui
     * préserve le bulletin algérien à l'identique.
     *
     * Ne retourne que les clés réellement présentes : un identifiant absent
     * n'est jamais rendu vide, il est omis.
     *
     * @return array<string, string>
     */
    private function companyLegalIdentifiers(?Company $company, ?string $countryCode = null): array
    {
        if ($company === null) {
            return [];
        }

        $code = strtoupper(trim((string) $countryCode));
        $keys = self::COUNTRY_LEGAL_IDENTIFIERS[$code] ?? self::DEFAULT_LEGAL_IDENTIFIERS;

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
