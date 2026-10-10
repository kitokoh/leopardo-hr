<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Offre de dispatch inexistante ou périmée (BC-34 VTC, VTC-04/#8360).
 *
 * Levée quand un chauffeur répond à une offre qui n'est plus l'offre
 * courante de la course (jamais faite, déjà expirée, déclinée ou course
 * déjà acceptée par un autre) — 409 fail-closed, code stable
 * VTC_OFFER_NOT_CURRENT. Avec le verrou `lockForUpdate` de
 * VtcDispatchService::acceptOffer, c'est ce qui rend une double
 * acceptation impossible.
 */
final class VtcOfferNotCurrentException extends DomainException
{
    public function __construct(int $rideId, int $driverId)
    {
        parent::__construct(
            "Aucune offre courante sur la course {$rideId} pour le chauffeur {$driverId}.",
            409,
            'VTC_OFFER_NOT_CURRENT'
        );
    }
}
