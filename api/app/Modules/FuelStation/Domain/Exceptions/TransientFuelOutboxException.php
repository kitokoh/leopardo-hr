<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;

use RuntimeException;

/**
 * FUEL-015 (#5809) — Erreur TRANSITOIRE d'un consommateur d'outbox
 * FuelStation : le retry avec backoff est approprié (provider indisponible,
 * timeout, 5xx).
 */
class TransientFuelOutboxException extends RuntimeException {}
