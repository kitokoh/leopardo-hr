<?php

declare(strict_types=1);

namespace App\Rules;

use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MULTI-PAYS (#1951) — pays SUPPORTÉ AU SENS PAIE : le code doit appartenir
 * au résolveur de règles pays (`CountryRulesResolver::supports()`), c.-à-d.
 * disposer de règles de calcul réelles (19 pays), et pas seulement figurer au
 * registre d'affichage `CountryDefaults` (21 codes, dont GB/US sans règles).
 *
 * Un tenant GB/US peut exister (HR sans paie), mais AUCUN run/structure/
 * barème/simulation ne doit accepter un pays sans règles : le calcul lèverait
 * `UnsupportedCountryRulesException` (422/500) en plein run.
 *
 * Remplace les listes `in:DZ,MA,...` hardcodées (3 définitions divergentes,
 * issue #1951) par le contrat partagé `supportedCountryCodes()`.
 */
final class PayrollSupportedCountry implements ValidationRule
{
    public function __construct(private readonly ?CountryRulesResolver $resolver = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $resolver = $this->resolver ?? app(CountryRulesResolver::class);

        if (! is_string($value) || ! $resolver->supports($value)) {
            $fail('validation.supported_country')->translate([
                'attribute' => $attribute,
                'value' => is_scalar($value) ? (string) $value : '?',
            ]);
        }
    }
}
