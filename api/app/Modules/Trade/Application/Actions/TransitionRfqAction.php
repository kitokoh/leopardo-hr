<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeRfqStatus;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Support\TradeRfqStateMachine;
use Illuminate\Database\DatabaseManager;

/**
 * Transition de statut d'une demande de prix (RFQ) — BC-36 TRADE, #9779.
 *
 * ## Pourquoi cette action existe
 *
 * `TradeRfqStateMachine` implementait deja tout le cycle
 * (`draft -> open -> awarded | closed | cancelled`), et `SendRfqAction`
 * n'utilisait que le passage `draft -> open`. **Aucune route ne permettait
 * d'en sortir** : une RFQ diffusee restait `open` pour toujours.
 *
 * Consequence concrete pour un vendeur B2B : il pouvait demander des prix a
 * ses fournisseurs, recevoir les offres... et ne jamais pouvoir designer le
 * gagnant (`awarded`) ni clore la consultation (`closed`). Le sourcing
 * s'arretait au moment precis ou il fallait decider. `awarded_quote_id` et
 * `awarded_at` existaient en base et ne pouvaient jamais etre renseignes.
 *
 * ## Ce que cette action ne fait PAS
 *
 * Elle ne reimplemente aucune regle : le verdict reste a
 * `TradeRfqStateMachine`, seule source de verite. Dupliquer la table de
 * transitions ici ferait diverger deux verites.
 *
 * Elle ne cree pas non plus de commande : attribuer une RFQ designe une offre
 * gagnante, il ne l'accepte pas. L'acceptation reste `AcceptQuoteAction`, un
 * acte distinct qui a ses propres effets (creation de commande).
 */
final class TransitionRfqAction
{
    public function __construct(
        private readonly TradeRfqStateMachine $states,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{awarded_quote_id?: int|null, close_reason?: string|null}  $payload
     *
     * @throws \App\Modules\Trade\Domain\Exceptions\InvalidTradeStateTransitionException
     */
    public function execute(TradeRfq $rfq, TradeRfqStatus $target, array $payload = []): TradeRfq
    {
        return $this->db->transaction(function () use ($rfq, $target, $payload): TradeRfq {
            // Verrou de ligne : deux transitions concurrentes sur la meme RFQ
            // ne doivent pas produire deux etats incoherents (par exemple
            // `awarded` puis `closed` sur la meme consultation).
            /** @var TradeRfq $locked */
            $locked = TradeRfq::query()->whereKey($rfq->id)->lockForUpdate()->firstOrFail();

            $this->states->assertCanTransitionTo($locked->status, $target);

            $locked->status = $target;

            match ($target) {
                // `awarded` est TERMINAL : on horodate la decision et on
                // retient l'offre gagnante. Sans `awarded_quote_id`, l'etat
                // dirait « une offre a gagne » sans dire laquelle — ce n'est
                // pas une decision, c'est une case cochee.
                TradeRfqStatus::Awarded => $this->award($locked, $payload),
                TradeRfqStatus::Closed => $locked->closed_at = now(),
                // `open` : la date de publication est celle de la diffusion
                // reelle. `SendRfqAction` la pose deja ; on ne l'ecrase pas
                // lors d'une re-diffusion idempotente.
                TradeRfqStatus::Open => $locked->published_at ??= now(),
                default => null,
            };

            $locked->save();

            return $locked->refresh();
        });
    }

    /**
     * @param  array{awarded_quote_id?: int|null}  $payload
     */
    private function award(TradeRfq $rfq, array $payload): void
    {
        $rfq->awarded_at = now();

        if (isset($payload['awarded_quote_id'])) {
            $rfq->awarded_quote_id = (int) $payload['awarded_quote_id'];
        }
    }
}
