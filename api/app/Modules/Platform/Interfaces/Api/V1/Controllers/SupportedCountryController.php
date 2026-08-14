<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Infrastructure\Services\ComplianceWarningLocalizer;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Support\CountryDefaults;
use Illuminate\Http\JsonResponse;

/**
 * MULTI-PAYS (#1867) — registre unique des pays supportés.
 *
 * Expose pour chaque pays du référentiel : code ISO, libellé, langue,
 * devise, fuseau horaire, niveau de confiance des règles de paie,
 * avertissement de conformité localisé et statut de disponibilité (règles
 * de paie résolubles ou non).
 *
 * Issue #1872 — l'avertissement de conformité (compliance_warning) est
 * exposé pour chaque pays afin qu'aucun manager ne confonde une règle
 * pilote/placeholder avec une paie légalement certifiée. Message localisé
 * via le catalogue api/lang/*/payroll.php (payroll.confidence.*), avec repli
 * sur la disclosure des règles et message neutre pour les pays sans règles.
 *
 * C'est la source unique de vérité pour les formulaires de provisioning,
 * le cockpit et les écrans de calcul — aucun fallback silencieux.
 */
class SupportedCountryController extends Controller
{
    public function __construct(private readonly CountryRulesResolver $rulesResolver) {}

    public function index(): JsonResponse
    {
        $registry = [];

        foreach (CountryDefaults::all() as $country) {
            $code = $country['country'];

            try {
                $rules = $this->rulesResolver->resolve($code);
                $confidence = $rules->confidenceLevel();
                $warning = ComplianceWarningLocalizer::for($rules);
                $available = true;
            } catch (UnsupportedCountryRulesException) {
                // Pays référencé mais sans règles de paie dédiées (ex. GB/US) :
                // indisponible pour un calcul, pas une erreur.
                $confidence = 'unknown';
                $warning = ComplianceWarningLocalizer::unknown($code);
                $available = false;
            }

            $registry[] = [
                'country' => $code,
                'label' => $country['label'],
                'language' => $country['language'],
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
                'confidence' => $confidence,
                'compliance_warning' => $warning,
                'available' => $available,
            ];
        }

        return response()->json(['data' => $registry]);
    }
}
