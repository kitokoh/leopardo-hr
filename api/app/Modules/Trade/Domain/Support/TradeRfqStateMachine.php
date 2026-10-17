<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Support;

use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException;

/**
 * Machine a etats d'une demande de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Cycle : draft -> open -> (awarded | closed | cancelled) ; open -> closed.
 * `awarded` est terminal et marque la selection d'une offre gagnante.
 *
 * Classe PURE (aucune dependance Eloquent/DB) : testable en unite et
 * reutilisable par les actions et les jobs — meme approche que
 * VtcRideStateMachine (BC-35) et DeliveryStateMachine (BC-26).
 *
 * Toute transition illegale leve
 * {@see InvalidTradeStateTransitionException}, que le controleur traduit en
 * 422 `INVALID_STATE_TRANSITION`.
 */
final class TradeRfqStateMachine
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['open', 'cancelled'],
        // `open -> open` : re-diffusion idempotente (SendRfqAction documente
        // explicitement « draft (ou open) -> open »). Sans cette auto-transition,
        // une seconde diffusion a une contrepartie deja invitee etait refusee
        // en 422 au lieu d'etre un no-op silencieux.
        'open' => ['open', 'closed', 'awarded', 'cancelled'],
        // Etats terminaux : aucune transition sortante.
        'awarded' => [],
        'closed' => [],
        'cancelled' => [],
    ];

    public function assertCanTransitionTo(TradeRfqStatus $from, TradeRfqStatus $to): void
    {
        if (! $this->canTransitionTo($from, $to)) {
            throw new InvalidTradeStateTransitionException('RFQ', $from->value, $to->value);
        }
    }

    public function canTransitionTo(TradeRfqStatus $from, TradeRfqStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED_TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<string>
     */
    public function allowedFrom(TradeRfqStatus $from): array
    {
        return self::ALLOWED_TRANSITIONS[$from->value];
    }
}
