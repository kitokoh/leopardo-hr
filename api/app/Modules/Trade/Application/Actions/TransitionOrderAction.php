<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Modules\Trade\Domain\Models\TradeOrder;
use App\Modules\Trade\Domain\Support\TradeOrderStateMachine;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

/**
 * Transition de statut d'une commande — BC-36 TRADE, #9779.
 *
 * ## Pourquoi cette action existe
 *
 * `TradeOrderStateMachine` implementait deja tout le cycle
 * (`confirmed -> preparing -> ready -> shipped -> delivered -> closed`, plus
 * l'annulation avant expedition), mais **aucune route ne l'appelait** :
 * `AcceptQuoteAction` cree la commande en `confirmed` et rien ne permettait
 * d'en sortir. Une commande confirmee restait donc figee pour toujours, et le
 * vendeur n'avait aucun moyen de dire « c'est en preparation », « c'est
 * expedie », « c'est livre » — c'est-a-dire aucun outil pour GERER sa vente.
 *
 * Cette action ne reimplemente AUCUNE regle : elle delegue le verdict a la
 * machine a etats, qui reste la seule source de verite. Dupliquer la table de
 * transitions ici ferait diverger deux verites.
 *
 * ## Cycle de vie honnete
 *
 * Les horodatages sont poses pour ce qu'ils signifient : `shipped_at` est la
 * date d'EXPEDITION reelle (pas la date du clic), `delivered_at` la date de
 * LIVRAISON. Aucun ne doit etre pre-rempli a la creation : un `delivered_at`
 * pose d'avance ferait croire a une livraison qui n'a pas eu lieu.
 */
final class TransitionOrderAction
{
    public function __construct(
        private readonly TradeOrderStateMachine $states,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{cancel_reason?: string|null, expected_delivery_at?: string|null}  $payload
     *
     * @throws \App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException
     */
    public function execute(TradeOrder $order, TradeOrderStatus $target, array $payload = []): TradeOrder
    {
        return $this->db->transaction(function () use ($order, $target, $payload): TradeOrder {
            // Verrou de ligne : deux transitions concurrentes sur la meme
            // commande ne doivent pas produire deux etats incoherents.
            /** @var TradeOrder $locked */
            $locked = TradeOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $this->states->assertCanTransitionTo($locked->status, $target);

            $locked->status = $target;

            match ($target) {
                // Le motif est EXIGE par la requete pour `cancelled` : on le
                // stocke tel quel, il explique la commande des mois plus tard.
                TradeOrderStatus::Cancelled => $locked->cancelled_at = now(),
                TradeOrderStatus::Shipped => $locked->shipped_at = now(),
                TradeOrderStatus::Delivered => $locked->delivered_at = now(),
                TradeOrderStatus::Closed => $locked->closed_at = now(),
                default => null,
            };

            if (isset($payload['cancel_reason'])) {
                $locked->cancel_reason = $payload['cancel_reason'];
            }

            if (isset($payload['expected_delivery_at'])) {
                // La requete valide une DATE (chaine ISO) ; la colonne est un
                // `datetime`. Assigner la chaine brute laissait PHPStan (et un
                // cast implicite) decider du format : on parse explicitement.
                $locked->expected_delivery_at = Carbon::parse($payload['expected_delivery_at']);
            }

            $locked->save();

            return $locked->refresh();
        });
    }
}
