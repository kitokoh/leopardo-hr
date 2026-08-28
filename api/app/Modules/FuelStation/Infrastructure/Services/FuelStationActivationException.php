<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Infrastructure\Services;

/**
 * Activation FuelStation refusée (dépendance manquante, tenant inactif).
 */
final class FuelStationActivationException extends \RuntimeException
{
}
