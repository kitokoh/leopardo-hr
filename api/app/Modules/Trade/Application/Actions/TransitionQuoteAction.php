<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Domain\Support\TradeQuoteStateMachine;
use Illuminate\Database\DatabaseManager;

/**
 * Transition de statut d'une offre commerciale — BC-36 TRADE, #9779.
 *
 * ## Pourquoi cette action existe
 *
 * `TradeQuoteStateMachine` implementait tout le cycle
 * (`draft -> submitted -> under_review -> negotiating -> accepted | rejected |
 * withdrawn | expired`), mais seuls deux chemins etaient branches :
 * `SubmitQuoteAction` (`draft -> submitted`) et `AcceptQuoteAction`
 * (`-> accepted`).
 *
 * Un vendeur B2B pouvait donc emettre une offre et l'accepter — mais **pas la
 * refuser, ni la retirer, ni la constater expiree**. Or ce sont precisement
 * les trois issues qui arrivent le plus souvent : un client refuse, un
 * fournisseur retire son prix, une validite passe. Sans elles, une offre
 * refusee restait `submitted` indefiniment et polluait le pipeline : le
 * vendeur voyait « en attente » des affaires deja mortes.
 *
 * ## Ce que cette action ne fait PAS
 *
 * - Elle ne cree pas de commande : `accepted` passe par `AcceptQuoteAction`,
 *   qui a ses propres effets. Passer `accepted` ici serait un contournement
 *   silencieux de la creation de commande.
 * - Elle ne reimplemente aucune regle : le verdict reste a
 *   `TradeQuoteStateMachine`.
 */
final class TransitionQuoteAction
{
    public function __construct(
        private readonly TradeQuoteStateMachine $states,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{reason?: string|null}  $payload
     *
     * @throws \App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException
     */
    public function execute(TradeQuote $quote, TradeQuoteStatus $target, array $payload = []): TradeQuote
    {
        return $this->db->transaction(function () use ($quote, $target, $payload): TradeQuote {
            // Verrou de ligne : deux transitions concurrentes sur la meme
            // offre ne doivent pas produire deux etats incoherents.
            /** @var TradeQuote $locked */
            $locked = TradeQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            $this->states->assertCanTransitionTo($locked->status, $target);

            $locked->status = $target;

            match ($target) {
                // Chaque issue terminale a son propre horodatage : ils
                // repondent a des questions differentes (« quand le client
                // a-t-il refuse ? » n'est pas « quand l'avons-nous retiree ? »).
                TradeQuoteStatus::Rejected => $locked->rejected_at = now(),
                TradeQuoteStatus::Withdrawn => $locked->withdrawn_at = now(),
                TradeQuoteStatus::Submitted => $locked->submitted_at ??= now(),
                default => null,
            };

            if (isset($payload['reason'])) {
                // Le motif est conserve dans les notes : une offre refusee
                // sans raison ne dit rien au prochain commercial qui reprend
                // le dossier.
                $locked->notes = trim(($locked->notes ?? '')."\n".$payload['reason']);
            }

            $locked->save();

            return $locked->refresh();
        });
    }
}
