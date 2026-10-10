<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\Support;

use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Exceptions\InvalidRideTransitionException;

/**
 * Machine à états d'une course VTC (BC-34 VTC, VTC-03/#8359 — consommée par
 * le dispatch VTC-04 et l'API chauffeur VTC-05).
 *
 * Verrouille les invariants du cycle de vie (spec §5.2) :
 *  - requested → dispatching → accepted → arrived → in_progress → completed ;
 *  - expired : la fenêtre de dispatch s'est épuisée (aucune acceptation) ;
 *  - cancelled : depuis tout état avant le démarrage effectif de la course
 *    (motif tracé — la règle plus stricte « passager : avant accepted » est
 *    portée par CancelRideAction) ;
 *  - un état terminal (completed / expired / cancelled) est définitif —
 *    aucune réouverture.
 *
 * Classe pure (aucune dépendance Eloquent/DB) : testable en unité et
 * réutilisable par les actions, les jobs et l'API — même pattern que
 * DeliveryStateMachine (BC-26).
 */
final class VtcRideStateMachine
{
    /**
     * Transitions autorisées (source → destinations). Les clés sont les
     * valeurs de VtcRideStatus (miroir explicite — les cases d'enum ne sont
     * pas des expressions constantes en PHP 8.1).
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'requested' => ['dispatching', 'expired', 'cancelled'],
        'dispatching' => ['accepted', 'expired', 'cancelled'],
        'accepted' => ['arrived', 'cancelled'],
        'arrived' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        // États terminaux : aucune transition sortante.
        'completed' => [],
        'expired' => [],
        'cancelled' => [],
    ];

    /**
     * @var list<string>
     */
    private const TERMINAL_STATUSES = ['completed', 'expired', 'cancelled'];

    /**
     * Vérifie qu'une transition est légale ; lève une exception sinon.
     *
     * @throws InvalidRideTransitionException
     */
    public function assertCanTransitionTo(VtcRideStatus $from, VtcRideStatus $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from->value];

        if (! in_array($to->value, $allowed, true)) {
            throw new InvalidRideTransitionException($from, $to);
        }
    }

    /**
     * True si la transition est légale.
     */
    public function canTransitionTo(VtcRideStatus $from, VtcRideStatus $to): bool
    {
        try {
            $this->assertCanTransitionTo($from, $to);

            return true;
        } catch (InvalidRideTransitionException) {
            return false;
        }
    }

    /**
     * True si le statut est terminal (aucune transition sortante).
     */
    public function isTerminal(VtcRideStatus $status): bool
    {
        return in_array($status->value, self::TERMINAL_STATUSES, true);
    }
}
