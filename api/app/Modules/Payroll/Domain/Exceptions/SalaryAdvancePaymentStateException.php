<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Exceptions;

/**
 * Plan 60 — double validation des avances : l'update conditionnel atomique de
 * declaration de paiement (#3429/#2997, anti-TOCTOU) a affecte 0 ligne - soit
 * l'avance a déjà été déclarée payée (conflit), soit elle n'existe plus dans
 * la societe de l'acteur. L'interface distingue les deux cas (404 vs 422).
 */
class SalaryAdvancePaymentStateException extends \RuntimeException {}
