<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;

/**
 * Issue #1872 — règle factice au niveau de confiance 'production'.
 *
 * Aucune règle réelle du moteur n'a encore atteint 'production' (toutes les
 * juridictions sont pilot/placeholder au moment de #1872) : ce stub hérite
 * des implémentations concrètes de CanadaPayrollRules (calculs, barèmes,
 * cotisations) et ne change que le code pays + le niveau de confiance, ce
 * qui permet de tester l'exposition du niveau 'production' par le
 * présentateur de calcul et l'API de simulation.
 */
final class ProductionConfidenceRules extends CanadaPayrollRules
{
    public function countryCode(): string
    {
        return 'ZZ';
    }

    public function confidenceLevel(): string
    {
        return 'production';
    }
}
