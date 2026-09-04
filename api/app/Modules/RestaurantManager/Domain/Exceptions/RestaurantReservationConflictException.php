<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Exceptions;

use RuntimeException;

/**
 * RESTO-601 (#6206) — Conflit de créneau de réservation.
 *
 * Deux réservations ne peuvent pas se chevaucher sur la même table
 * (critère d'acceptation RESTO-601). Le contrôleur traduit cette exception
 * en HTTP 409.
 */
final class RestaurantReservationConflictException extends RuntimeException
{
}
