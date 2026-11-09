<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Enums;

/**
 * Type de mouvement de tiroir-caisse du POS Retail (BC-17 RETAIL, #9212).
 *
 * - `in`   : entree d'especes (appoint de monnaie, remboursement d'un
 *            collaborateur) — augmente l'attendu de cloture ;
 * - `out`  : sortie d'especes (achat urgent, avance) — diminue l'attendu ;
 * - `drop` : prelevement / remise en banque — diminue l'attendu.
 *
 * Sans ces mouvements, l'ecart de cloture etait inexplicable : un prelevement
 * pour la banque ressemblait a un manquant.
 */
enum RetailCashMovementType: string
{
    case In = 'in';

    case Out = 'out';

    case Drop = 'drop';

    /**
     * Signe du mouvement sur l'attendu de caisse : `in` credite, `out` et
     * `drop` debitent.
     */
    public function sign(): int
    {
        return match ($this) {
            self::In => 1,
            self::Out, self::Drop => -1,
        };
    }
}
