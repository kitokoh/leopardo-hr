<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

/**
 * Erreur transitoire de consommation outbox FuelStation (FUEL-015, #5809)
 * — retry avec backoff exponentiel.
 */
final class TransientFuelOutboxException extends \RuntimeException {}
