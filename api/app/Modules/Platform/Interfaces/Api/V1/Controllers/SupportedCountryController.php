<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function index(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $registry = [];

        foreach (CountryDefaults::all() as $country) {
            $code = $country['country'];

            try {
                $rules = $this->payrollCalculator->getRules($code);
                $confidence = $rules->confidenceLevel();
                $available = true;
            } catch (UnsupportedCountryRulesException) {
                // Pays référencé mais sans règles de paie dédiées : indisponible
                // pour un calcul, pas une erreur. (Depuis #5255, plus aucun pays
                // CountryDefaults n'est sans règles — GB/US livrés — mais la
                // garde reste pour un futur pays display-only.)
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

        // Issue #4502 : registre quasi-statique — directives de cache public
        // (1 h) + ETag pour que les apps mobiles pré-login ne rebrûlent pas le
        // bucket public-registry 60/min à chaque lancement. `warning_localized`
        // est locale-dépendant → Vary: Accept-Language obligatoire.
        $etag = sprintf('W/"%s"', sha1(serialize($registry)));

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Vary', 'Accept-Language');
        }

        return response()->json(['data' => $registry])
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Vary', 'Accept-Language');
    }
}
