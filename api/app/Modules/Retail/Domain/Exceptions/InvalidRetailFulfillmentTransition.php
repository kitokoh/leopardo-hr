<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Exceptions;

use App\Modules\Retail\Domain\Enums\RetailFulfillmentStatus;
use Exception;

/**
 * Transition de cycle de vie refusee sur une commande en ligne (BC-17,
 * #7808) : l'etat courant n'autorise pas la cible demandee (spec §3.3).
 * Traduite en 422 `INVALID_TRANSITION` par le controleur vendeur.
 */
class InvalidRetailFulfillmentTransition extends Exception
{
    public function __construct(
        public readonly RetailFulfillmentStatus $from,
        public readonly RetailFulfillmentStatus $to,
    ) {
        parent::__construct(sprintf(
            'Invalid fulfillment transition %s -> %s.',
            $from->value,
            $to->value,
        ));
    }
}
