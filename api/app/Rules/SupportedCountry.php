<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\CountryDefaults;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * MULTI-PAYS (#1867) — le code pays doit appartenir au registre des pays
 * supportés (CountryDefaults). Aucun fallback silencieux : un code absent,
 * inconnu ou non supporté est rejeté avec un message explicite.
 */
final class SupportedCountry implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CountryDefaults::isSupported($value)) {
            $fail('validation.supported_country')->translate([
                'attribute' => $attribute,
                'value' => is_scalar($value) ? (string) $value : '?',
            ]);
        }
    }
}
