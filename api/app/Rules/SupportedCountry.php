<?php

declare(strict_types=1);

namespace App\Rules;

use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Support\CountryDefaults;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MULTI-PAYS (#1867/#1951) — le code pays doit être un pays SUPPORTÉ :
 *
 *   1. présent dans le registre d'affichage `CountryDefaults` (21 codes) ;
 *   2. ET disposer de règles de paie résolubles (`CountryRulesResolver::supports()`).
 *
 * La double condition élimine la divergence #1951 : GB/US sont dans le
 * registre de display mais n'ont AUCUNE règle de paie — accepter leur code
 * au moment de la validation (création de run, trial) menait à une
 * `UnsupportedCountryRulesException` au calcul (422/500) au lieu d'un 422
 * propre et immédiat. Aucun fallback silencieux : un code absent, inconnu ou
 * sans règles est rejeté avec un message explicite.
 */
final class SupportedCountry implements ValidationRule
{
    private readonly CountryRulesResolver $resolver;

    /**
     * @param  CountryRulesResolver|null  $resolver  règles custom (tests) ; null → registre par défaut
     */
    public function __construct(?CountryRulesResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new CountryRulesResolver;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CountryDefaults::isSupported($value)) {
            $fail('validation.supported_country')->translate([
                'attribute' => $attribute,
                'value' => is_scalar($value) ? (string) $value : '?',
            ]);

            return;
        }

        // #1951 : pays référencé mais SANS règles de paie (ex. GB/US) →
        // rejeté ici plutôt qu'au calcul.
        if (! $this->resolver->supports($value)) {
            $fail('validation.country_without_payroll_rules')->translate([
                'attribute' => $attribute,
                'value' => strtoupper(trim($value)),
            ]);
        }
    }
}
