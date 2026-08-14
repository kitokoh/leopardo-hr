<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #1767 — Le calcul d'un run de paie n'a produit AUCUN bulletin.
 *
 * Cas typiques : aucune structure salariale active pour le pays du run, ou
 * aucun employé actif. Avant ce garde-fou, le run passait en `calculated` en
 * silence avec `employee_count: 0` et pouvait être validé/verrouillé à vide
 * (clôture comptable erronée). L'exception est levée DANS la transaction du
 * calcul (rollback : les anciens bulletins ne sont pas détruits) et traduite
 * en HTTP 422 par le contrôleur.
 */
class PayrollEmptyRunException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422, 'PAYROLL_EMPTY_RUN');
    }
}
