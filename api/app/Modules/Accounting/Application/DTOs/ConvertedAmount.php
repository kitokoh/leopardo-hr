<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\DTOs;

use App\Modules\Accounting\Application\Actions\DocumentCurrencyConverter;

/**
 * Résultat d'une conversion de montant — issue #5270.
 *
 * Montant exprimé dans la devise source et la devise cible, avec le taux
 * appliqué et sa source (identité / manuel / externe) — arrondi documenté
 * (half-up, 2 décimales, voir DocumentCurrencyConverter).
 */
final readonly class ConvertedAmount
{
    public function __construct(
        public float $amount,
        public string $fromCurrency,
        public string $toCurrency,
        public float $rate,
        public float $convertedAmount,
        public string $source,
        public int $decimals = DocumentCurrencyConverter::DECIMALS,
        public string $rounding = DocumentCurrencyConverter::ROUNDING_MODE,
    ) {}
}
