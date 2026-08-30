<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Support;

use App\Modules\Delivery\Domain\Enums\DeliveryStatus;
use App\Modules\Delivery\Domain\Exceptions\InvalidDeliveryTransitionException;

/**
 * Machine à états d'une livraison (BC-26 DELIVERY, DELIVERY-103/#6284).
 *
 * Verrouille les invariants du cycle de vie :
 *  - une livraison ne peut atteindre un état terminal (delivered / returned /
 *    cancelled) qu'une seule fois — aucune réouverture ;
 *  - `delivered` exige une preuve de livraison (POD photo/signature) ;
 *  - pas de saut d'étape (ex. `assigned` → `delivered` refusé).
 *
 * Classe pure (aucune dépendance Eloquent/DB) : testable en unité et
 * réutilisable par les agrégats, les commandes et l'API.
 */
final class DeliveryStateMachine
{
    /**
     * Transitions autorisées (source → destinations). Les clés sont les
     * valeurs de DeliveryStatus (miroir explicite — les cases d'enum ne sont
     * pas des expressions constantes en PHP 8.1).
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'created' => ['assigned', 'cancelled'],
        'assigned' => ['picked_up', 'cancelled'],
        'picked_up' => ['out_for_delivery', 'failed'],
        'out_for_delivery' => ['arrived', 'failed'],
        'arrived' => ['delivered', 'failed'],
        'failed' => ['returned'],
        // États terminaux : aucune transition sortante.
        'delivered' => [],
        'returned' => [],
        'cancelled' => [],
    ];

    /**
     * @var list<string>
     */
    private const TERMINAL_STATUSES = ['delivered', 'returned', 'cancelled'];

    /**
     * Vérifie qu'une transition est légale ; lève une exception sinon.
     *
     * @throws InvalidDeliveryTransitionException
     */
    public function assertCanTransitionTo(
        DeliveryStatus $from,
        DeliveryStatus $to,
        bool $hasProof = false,
    ): void {
        $allowed = self::ALLOWED_TRANSITIONS[$from->value];

        if (! in_array($to->value, $allowed, true)) {
            throw InvalidDeliveryTransitionException::notAllowed($from, $to);
        }

        if ($to === DeliveryStatus::Delivered && ! $hasProof) {
            throw InvalidDeliveryTransitionException::proofRequired($to);
        }
    }

    /**
     * True si la transition est légale (avec ou sans preuve).
     */
    public function canTransitionTo(DeliveryStatus $from, DeliveryStatus $to, bool $hasProof = false): bool
    {
        try {
            $this->assertCanTransitionTo($from, $to, $hasProof);

            return true;
        } catch (InvalidDeliveryTransitionException) {
            return false;
        }
    }

    /**
     * True si le statut est terminal (aucune transition sortante).
     */
    public function isTerminal(DeliveryStatus $status): bool
    {
        return in_array($status->value, self::TERMINAL_STATUSES, true);
    }
}
