<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\DTOs;

use App\Modules\Accounting\Application\Actions\DocumentCurrencyConverter;

/**
 * Totaux d'un document comptable dans la devise du document ET dans la
 * devise de référence — issue #5270 (« Totaux dans la devise du document
 * + devise de référence »).
 *
 * La TVA est TOUJOURS calculée dans la devise du document (montants des
 * lignes), puis les totaux (HT, TVA, TTC) sont convertis dans la devise de
 * référence — jamais de taux appliqué avant le calcul de la TVA (règle
 * documentée dans .specify/features/5270-multi-currency/spec.md).
 */
final readonly class ConvertedTotals
{
    public function __construct(
        public string $documentCurrency,
        public string $referenceCurrency,
        public float $rate,
        public string $source,
        public float $subtotalHt,
        public float $taxAmount,
        public float $totalTtc,
        public float $subtotalHtConverted,
        public float $taxAmountConverted,
        public float $totalTtcConverted,
        public int $decimals = DocumentCurrencyConverter::DECIMALS,
        public string $rounding = DocumentCurrencyConverter::ROUNDING_MODE,
    ) {}
}
