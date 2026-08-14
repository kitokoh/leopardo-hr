<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;

/**
 * MULTI-PAYS (#1867) — registre unique des pays supportés.
 *
 * Expose pour chaque pays du référentiel : code ISO, libellé, langue,
 * devise, fuseau horaire, niveau de confiance des règles de paie et statut
 * de disponibilité (règles de paie résolubles ou non).
 *
 * C'est la source unique de vérité pour les formulaires de provisioning,
 * le cockpit et les écrans de calcul — aucun fallback silencieux.
 */
class SupportedCountryController extends Controller
{
    public function __construct(private readonly PayrollCalculator $payrollCalculator) {}

    public function index(): JsonResponse
    {
        $registry = [];

        foreach (CountryDefaults::all() as $country) {
            $code = $country['country'];

            try {
                $rules = $this->payrollCalculator->getRules($code);
                $available = true;
            } catch (UnsupportedCountryRulesException) {
                // Pays référencé mais sans règles de paie dédiées (ex. GB/US) :
                // indisponible pour un calcul, pas une erreur.
                $rules = null;
                $available = false;
            }

            // Issue #1872 — avertissement de conformité exposé aux clients
            // web/mobile : niveau de confiance, message localisé, sources et
            // date de vérification experte (null tant que #1904/#1912 n'ont
            // pas validé).
            $registry[] = [
                'country' => $code,
                'label' => $country['label'],
                'language' => $country['language'],
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
                'confidence' => $rules?->confidenceLevel() ?? 'unknown',
                'available' => $available,
                'compliance_warning_key' => $rules?->complianceWarningKey() ?? null,
                'compliance_warning' => $rules !== null ? __($rules->complianceWarningKey(), ['country' => $code]) : null,
                'legal_sources' => $rules?->legalSources() ?? [],
                'verified_at' => $rules?->complianceVerifiedAt() ?? null,
            ];
        }

        return response()->json(['data' => $registry]);
    }
}
