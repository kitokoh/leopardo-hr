<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

/**
 * Erreur permanente de consommation outbox FuelStation (FUEL-015, #5809)
 * — dead-letter immédiate (statut failed), rejouable manuellement.
 */
final class PermanentFuelOutboxException extends \RuntimeException {}
