<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Enums;

/**
 * TRAVEL-802 (#6093) — Statut dérivé d'un aller-retour.
 *
 * Le statut n'est pas persisté : il est calculé depuis le statut des deux
 * réservations liées (chaque sens reste une réservation indépendante,
 * annulable par sens). Valeurs exposées par l'API uniquement.
 */
enum RoundTripStatus: string
{
    case ACTIVE = 'active';
    case PARTIALLY_CANCELLED = 'partially_cancelled';
    case CANCELLED = 'cancelled';
}
