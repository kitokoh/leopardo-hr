<?php

declare(strict_types=1);

namespace App\Rules;

use App\Modules\Payroll\Infrastructure\Services\CountryRulesResolver;
use App\Support\CountryDefaults;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MULTI-PAYS (#1867/#1951) — le code pays doit appartenir au registre des
 * pays supportés (CountryDefaults) ET disposer de règles de paie résolubles
 * (CountryRulesResolver). Aucun fallback silencieux : un code absent,
 * inconnu, référencé sans règles (ex. GB/US) ou non supporté est rejeté
 * avec un message explicite — la validation d'un run ne doit plus accepter
 * un pays qui échouera au moment du calcul (UnsupportedCountryRulesException).
 */
final class SupportedCountry implements ValidationRule
{
    public function __construct(private readonly ?CountryRulesResolver $resolver = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $resolver = $this->resolver ?? new CountryRulesResolver;

        if (! is_string($value) || ! CountryDefaults::isSupported($value) || ! $resolver->supports($value)) {
            $fail('validation.supported_country')->translate([
                'attribute' => $attribute,
                'value' => is_scalar($value) ? (string) $value : '?',
            ]);
        }
    }
}
