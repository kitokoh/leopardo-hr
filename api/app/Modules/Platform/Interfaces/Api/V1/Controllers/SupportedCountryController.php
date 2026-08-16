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
                $confidence = $rules->confidenceLevel();
                $available = true;
            } catch (UnsupportedCountryRulesException) {
                // Pays référencé mais sans règles de paie dédiées (ex. GB/US) :
                // indisponible pour un calcul, pas une erreur.
                $rules = null;
                $confidence = 'unknown';
                $available = false;
            }

            // Issue #2127 — bloc conformité structuré (contrat #1872), même
            // vocabulaire que PayrollCalculationPresenter : niveau de
            // confiance, avertissement, message localisé, source légale et
            // date de vérification experte (nullable).
            if ($rules !== null) {
                $compliance = [
                    'level' => $rules->confidenceLevel(),
                    'warning' => $rules->complianceWarning(),
                    'warning_localized' => __('payroll.compliance_warning_'.$rules->confidenceLevel()),
                    'source' => $rules->complianceSource(),
                    'verified_at' => $rules->verificationDate(),
                ];
            } else {
                // #4446 : le champ brut `warning` suit la langue des règles
                // (EN pour pilot/placeholder, source docs) — le littéral FR
                // cassait la cohérence du registre public. `warning_localized`
                // reste localisé selon la requête.
                $compliance = [
                    'level' => 'unknown',
                    'warning' => __('payroll.compliance_warning_unknown', [], 'en'),
                    'warning_localized' => __('payroll.compliance_warning_unknown'),
                    'source' => null,
                    'verified_at' => null,
                ];
            }

            $registry[] = [
                'country' => $code,
                'label' => $country['label'],
                'language' => $country['language'],
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
                'confidence' => $confidence,
                'available' => $available,
                'compliance' => $compliance,
            ];
        }

        return response()->json(['data' => $registry]);
    }
}
