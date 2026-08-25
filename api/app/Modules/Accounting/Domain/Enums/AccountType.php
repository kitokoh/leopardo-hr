<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Nature comptable d'un compte du plan — détermine son traitement dans les
 * états financiers (bilan vs compte de résultat) et le sens des soldes.
 *
 * Issue #5422 — plan comptable paramétrable.
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * Un compte de bilan est une position (actif/passif/capitaux) ; les
     * comptes de gestion (charges/produits) alimentent le compte de résultat.
     */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
