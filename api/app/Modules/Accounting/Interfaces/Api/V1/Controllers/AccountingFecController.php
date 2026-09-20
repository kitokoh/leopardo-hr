<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Domain\Models\AccountingJournalEntry;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Exports\FecExporter;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

/**
 * Export FEC — Fichier des Écritures Comptables (norme DGFiP, issues #5422
 * et #7927).
 *
 * GET /api/v1/accounting/journal/export-fec?period=YYYY-MM
 *
 * Génère le fichier FEC de la période pour l'expert-comptable (13 colonnes,
 * CSV UTF-8). RBAC : principal/comptable (même périmètre que le journal).
 *
 * Issue #7927 :
 *   - la devise des colonnes Devise/MontantDevise est résolue depuis les
 *     réglages comptables du tenant, sinon depuis son pays (CountryDefaults
 *     — EUR pour FR, DZD pour DZ…) ; plus de 'DZD' codé en dur ;
 *   - pour un tenant français, le nom de fichier suit la norme DGFiP
 *     `SIRENFECAAAAMMJJ` (art. A. 47 A-1 LPF) — le SIREN (9 chiffres) est
 *     un réglage de l'entreprise (metadata `siren`, ou dérivé du `siret`) ;
 *     absent ou invalide → 422 explicite (aucun nom deviné).
 */
final class AccountingFecController extends Controller
{
    public function __construct(private readonly FecExporter $exporter) {}

    /**
     * Export FEC de la période demandée.
     */
    public function export(Request $request): Response|JsonResponse
    {
        $validated = Validator::make($request->only('period'), [
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'period.required' => __('accounting.validation.period_required'),
            'period.regex' => __('accounting.validation.period_invalid'),
        ])->validate();

        $period = (string) $validated['period'];
        $companyId = (string) ($request->user()?->getAttribute('company_id') ?? '');

        if (! AccountingJournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period', $period)
            ->exists()) {
            return response()->json([
                'message' => __('accounting.fec_no_entries'),
                'code' => 'FEC_NO_ENTRIES',
            ], 422);
        }

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);
        $filename = $this->filename($company, $period);

        if ($filename instanceof JsonResponse) {
            return $filename;
        }

        $csv = $this->exporter->export($companyId, $period, $this->currency($company, $companyId));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Devise des colonnes Devise/MontantDevise (#7927) : réglage comptable
     * du tenant en priorité (provisionné depuis le pays), sinon devise du
     * registre pays (CountryDefaults), sinon défaut historique DZD
     * (entreprise sans pays supporté — comportement pré-#7927 conservé).
     */
    private function currency(?Company $company, string $companyId): string
    {
        /** @var AccountingSettings|null $settings */
        $settings = AccountingSettings::query()->where('company_id', $companyId)->first();

        $currency = $settings->currency
            ?? (CountryDefaults::find($company?->country)['currency'] ?? null)
            ?? $company?->currency;

        return strtoupper(trim((string) ($currency ?? 'DZD')));
    }

    /**
     * Nom du fichier exporté : norme DGFiP `SIRENFECAAAAMMJJ` pour un tenant
     * français (exercices civils → clôture au 31/12 de l'année de la
     * période), nom historique `fec-YYYY-MM.csv` sinon (non-régression).
     */
    private function filename(?Company $company, string $period): string|JsonResponse
    {
        if (strtoupper(trim((string) $company?->country)) !== 'FR') {
            return 'fec-'.$period.'.csv';
        }

        $siren = FecExporter::resolveSiren($company?->metadata);

        if ($siren === null) {
            return response()->json([
                'message' => __('accounting.fec_siren_missing'),
                'code' => 'FEC_SIREN_MISSING',
            ], 422);
        }

        if (! FecExporter::isValidSiren($siren)) {
            return response()->json([
                'message' => __('accounting.fec_siren_invalid'),
                'code' => 'FEC_SIREN_INVALID',
            ], 422);
        }

        return FecExporter::officialFrFilename($siren, (int) substr($period, 0, 4));
    }
}
