<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Exceptions;

use RuntimeException;

/**
 * RESTO-501 (#6200) — Erreur métier du stock RestaurantManager.
 *
 * Levée par `RestaurantStockMovementService` quand un invariant du stock est
 * violé (stock négatif, delta nul). Le contrôleur la traduit en HTTP 422
 * avec un message sûr (aucune donnée interne exposée).
 */
final class RestaurantStockException extends RuntimeException
{
}
