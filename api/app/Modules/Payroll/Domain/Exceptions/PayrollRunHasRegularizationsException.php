<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Issue #1942 — le run original porte des régularisations actives : il ne
 * peut être ni déverrouillé ni annulé (l'invariant « l'original n'est
 * jamais modifié » de #1818 tomberait).
 */
class PayrollRunHasRegularizationsException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce run possède des régularisations actives : il ne peut pas être déverrouillé ni annulé. Régularisez l\'inverse ou annulez les régularisations d\'abord.');
    }
}
