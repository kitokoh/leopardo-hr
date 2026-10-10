<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Support;

/**
 * Feature flags de la verticale FUNDRAISING (cagnottes solidaires).
 *
 * Le flag tenant `fundraising` est résolu via `Company::hasFeature()`
 * (JSON `companies.features`, mécanisme Core/Feature). Défaut fail-closed :
 * un tenant sans le flag ne voit aucune route de gestion (middleware
 * `module.fundraising`). Enregistré aux 3 points obligatoires :
 * `config/feature-flags.php`, `Company::KNOWN_MODULES` et catalogue produit
 * (leçon #7220/#7235/company_showcase).
 */
final class FundraisingFeatures
{
    /** Activation de la verticale Cagnottes solidaires pour un tenant. */
    public const FUNDRAISING = 'fundraising';
}
