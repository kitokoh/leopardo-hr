<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Events;

use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Émis à la clôture d'une session de caisse FuelStation (FUEL-007, #5801).
 *
 * Contrat Accounting (FUEL-015) : un listener du module Accounting peut
 * consommer cet événement pour générer les écritures comptables de la
 * session (état figé : expected_balance/variance calculés, statut closed).
 */
class FuelCashSessionClosed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FuelCashSession $session,
    ) {}
}
