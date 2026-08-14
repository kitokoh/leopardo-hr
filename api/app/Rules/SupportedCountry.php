<?php

declare(strict_types=1);

namespace App\Rules;

use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Support\CountryDefaults;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MULTI-PAYS (#1867/#1951) — le code pays doit être supporté pour la paie.
 *
 * Deux conditions (contre la dérive #1951 où 3 définitions du « pays
 * supporté » coexistaient) :
 *   1. présent dans le registre d'affichage `CountryDefaults` (21 codes) ;
 *   2. **règles de paie DISPONIBLES** via `CountryRulesResolver` (19 codes
 *      avec implémentation) — un pays sans règles (ex. GB/US) serait accepté
 *      par le registre puis échouerait au calcul avec
 *      `UnsupportedCountryRulesException` (invariant 3 de la spec).
 *
 * Aucun fallback silencieux : un code absent, inconnu ou sans règles est
 * rejeté avec un message explicite.
 */
final class SupportedCountry implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.supported_country')->translate([
                'attribute' => $attribute,
                'value' => '?',
            ]);

            return;
        }

        $code = strtoupper(trim($value));

        if (! CountryDefaults::isSupported($code)) {
            $fail('validation.supported_country')->translate([
                'attribute' => $attribute,
                'value' => $code,
            ]);

            return;
        }

        // #1951 — disponibilité des RÈGLES de paie, pas seulement le registre
        // d'affichage : un tenant GB/US ne doit pas pouvoir créer un run qui
        // échouera au calcul (UnsupportedCountryRulesException).
        if (! (new CountryRulesResolver)->supports($code)) {
            $fail('validation.supported_country_payroll')->translate([
                'attribute' => $attribute,
                'value' => $code,
            ]);
        }
    }
}
