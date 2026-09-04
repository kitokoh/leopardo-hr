<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Exceptions;
use RuntimeException;

/**
 * Erreur permanente d'un événement d'outbox FuelStation — dead-letter
 * immédiate (aucun retry).
 */
final class PermanentFuelOutboxException extends \RuntimeException {

}