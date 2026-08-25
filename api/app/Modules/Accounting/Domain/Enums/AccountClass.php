<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Enums;

/**
 * Classe PCG/SCF d'un compte du plan comptable.
 *
 *  1 — Comptes de capitaux (capitaux propres, emprunts)
 *  2 — Comptes d'immobilisations (actif durable)
 *  3 — Comptes de stocks et en-cours (actif circulant)
 *  4 — Comptes de tiers (clients, fournisseurs, organismes sociaux)
 *  5 — Comptes financiers (banques, caisse, valeurs mobilières)
 *  6 — Comptes de charges (compte de résultat)
 *  7 — Comptes de produits (compte de résultat)
 *  8 — Comptes spéciaux (hors bilan, engagements)
 *
 * Issue #5422 — plan comptable paramétrable.
 */
enum AccountClass: int
{
    case Equity = 1;
    case FixedAssets = 2;
    case Inventory = 3;
    case ThirdParty = 4;
    case Financial = 5;
    case Expenses = 6;
    case Revenue = 7;
    case Special = 8;
}
