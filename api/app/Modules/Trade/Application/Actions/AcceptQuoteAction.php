<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeOrderDirection;
use App\Modules\Trade\Domain\Enums\TradeOrderStatus;
use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Modules\Trade\Domain\Models\TradeOrder;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Domain\Support\TradeQuoteStateMachine;
use App\Modules\Trade\Infrastructure\Services\TradeReferenceGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

/**
 * Acceptation d'une offre, suivie de la creation de la commande — TRADE-004/#8479.
 *
 * C'est le POINT DE BASCULE du parcours : l'objet commercial change de nature
 * (une intention de prix devient un engagement). Deux invariants y sont
 * verrouilles :
 *
 *  1. **Seule une offre `submitted`/`under_review`/`negotiating` s'accepte.**
 *     La machine a etats refuse une offre deja acceptee (pas d'acceptation
 *     double) ou un brouillon jamais emis — on ne commande pas un prix que
 *     personne n'a confirme.
 *
 *  2. **Le total de la commande est REPRIS de l'offre, jamais du client.**
 *     Le montant engage est exactement celui que le serveur avait fige a
 *     l'emission de l'offre.
 *
 * Le sens de la commande est DEDUIT du sens de l'offre (offre entrante =
 * achat ; offre emise = vente) : une seule saisie produit la bonne semantique
 * cote acheteur comme cote vendeur.
 */
final class AcceptQuoteAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly TradeReferenceGenerator $references,
        private readonly TradeQuoteStateMachine $quoteStates,
        private readonly RecordTradeEventAction $recordEvent,
        private readonly DatabaseManager $db,
    ) {}

    public function execute(TradeQuote $quote, ?int $employeeId = null, ?string $expectedDeliveryAt = null): TradeOrder
    {
        $year = (int) now()->format('Y');

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->db->transaction(function () use ($quote, $employeeId, $expectedDeliveryAt, $year): TradeOrder {
                    // Verrou de ligne : deux acceptations concurrentes de la
                    // meme offre ne doivent pas produire deux commandes.
                    /** @var TradeQuote $locked */
                    $locked = TradeQuote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

                    $this->quoteStates->assertCanTransitionTo($locked->status, TradeQuoteStatus::Accepted);

                    $locked->status = TradeQuoteStatus::Accepted;
                    $locked->accepted_at = now();
                    $locked->save();

                    // Le sens de la commande decoule de celui de l'offre.
                    $direction = $locked->direction?->value === 'incoming'
                        ? TradeOrderDirection::Purchase
                        : TradeOrderDirection::Sale;

                    /** @var TradeOrder $order */
                    $order = TradeOrder::query()->create([
                        'reference' => $this->references->nextOrder($year),
                        'direction' => $direction->value,
                        'trade_deal_id' => $locked->trade_deal_id,
                        'trade_quote_id' => $locked->id,
                        'trade_counterparty_id' => $locked->trade_counterparty_id,
                        'status' => TradeOrderStatus::Confirmed->value,
                        'currency' => $locked->currency,
                        'incoterm' => $locked->incoterm,
                        // Repris de l'offre : jamais recalcule depuis une entree client.
                        'total_minor' => $locked->total_minor,
                        'payment_terms' => $locked->payment_terms,
                        'ordered_at' => now(),
                        'expected_delivery_at' => $expectedDeliveryAt,
                        'created_by_employee_id' => $employeeId,
                    ]);

                    $this->recordEvent->execute('quote', $locked->id, 'quote.accepted', [
                        'reference' => $locked->reference,
                        'order_reference' => $order->reference,
                        'total_minor' => $order->total_minor,
                        'currency' => $order->currency,
                    ], $employeeId);

                    $this->recordEvent->execute('order', $order->id, 'order.confirmed', [
                        'reference' => $order->reference,
                        'quote_reference' => $locked->reference,
                        'direction' => $direction->value,
                    ], $employeeId);

                    return $order;
                });
            } catch (QueryException $exception) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        // Inatteignable : la boucle retourne ou leve (garde PHPStan).
        throw new \RuntimeException('Acceptation d\'offre impossible apres plusieurs tentatives.');
    }
}
