<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeQuoteDirection;
use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Domain\Models\TradeQuoteLine;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Support\TradeQuoteStateMachine;
use App\Modules\Trade\Infrastructure\Services\TradeReferenceGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

/**
 * Emission d'une offre commerciale — BC-36 TRADE, TRADE-004/#8479.
 *
 * Objet UNIQUE vu des deux cotes : `incoming` quand l'offre repond a une RFQ
 * qu'on a emise (cote acheteur), `outgoing` quand on repond a une RFQ recue ou
 * qu'on propose spontanement (cote vendeur).
 *
 * Les TOTAUX SONT FIGES COTE SERVEUR : `line_total_minor` est calcule
 * (quantite × prix unitaire) et `subtotal_minor`/`total_minor` en decoulent.
 * Un client ne peut donc pas declarer un total incoherent avec ses lignes —
 * le prix affiche est toujours celui que le serveur a verifie.
 */
final class SubmitQuoteAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly TradeReferenceGenerator $references,
        private readonly TradeQuoteStateMachine $quoteStates,
        private readonly RecordTradeEventAction $recordEvent,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{currency: string, incoterm?: string|null, validity_until?: string|null, lead_time_days?: int|null, payment_terms?: string|null, notes?: string|null}  $data
     * @param  list<array{description: string, quantity: float|string, unit_price_minor: int, unit?: string|null, trade_product_id?: int|null, moq_quantity?: float|string|null, lead_time_days?: int|null}>  $lines
     */
    public function execute(
        array $data,
        array $lines,
        ?int $employeeId = null,
        ?int $rfqId = null,
        ?int $invitationId = null,
        ?int $counterpartyId = null,
        ?int $dealId = null,
        bool $submit = true,
    ): TradeQuote {
        $year = (int) now()->format('Y');

        // L'offre repond a une RFQ connue -> sens ENTRANT ; sinon EMISE.
        $direction = $rfqId !== null ? TradeQuoteDirection::Incoming : TradeQuoteDirection::Outgoing;

        // Le perimetre de cout herite de la RFQ : une offre repond a un
        // Incoterm donne, et c'est ce qui la rend comparable.
        $incoterm = $data['incoterm'] ?? null;

        if ($rfqId !== null && $incoterm === null) {
            $rfq = TradeRfq::query()->whereKey($rfqId)->first();
            $incoterm = $rfq instanceof TradeRfq ? $rfq->incoterm : null;
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->db->transaction(function () use ($data, $lines, $employeeId, $rfqId, $invitationId, $counterpartyId, $dealId, $direction, $incoterm, $year, $submit): TradeQuote {
                    /** @var TradeQuote $quote */
                    $quote = TradeQuote::query()->create([
                        'reference' => $this->references->nextQuote($year),
                        'version' => 1,
                        'supersedes_id' => null,
                        'direction' => $direction->value,
                        'trade_rfq_id' => $rfqId,
                        'trade_rfq_invitation_id' => $invitationId,
                        'trade_deal_id' => $dealId,
                        'trade_counterparty_id' => $counterpartyId,
                        'status' => TradeQuoteStatus::Draft->value,
                        'currency' => $data['currency'],
                        'incoterm' => $incoterm,
                        'validity_until' => $data['validity_until'] ?? null,
                        'lead_time_days' => $data['lead_time_days'] ?? null,
                        'payment_terms' => $data['payment_terms'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'created_by_employee_id' => $employeeId,
                    ]);

                    $subtotal = 0;

                    foreach (array_values($lines) as $position => $line) {
                        // Calcul SERVEUR du total de ligne : le client ne
                        // fournit jamais un total.
                        $lineTotal = (int) round(((float) $line['quantity']) * $line['unit_price_minor']);
                        $subtotal += $lineTotal;

                        TradeQuoteLine::query()->create([
                            'trade_quote_id' => $quote->id,
                            'trade_product_id' => $line['trade_product_id'] ?? null,
                            'description' => $line['description'],
                            'quantity' => (string) $line['quantity'],
                            'unit' => $line['unit'] ?? 'unit',
                            'unit_price_minor' => $line['unit_price_minor'],
                            'moq_quantity' => isset($line['moq_quantity']) ? (string) $line['moq_quantity'] : null,
                            'lead_time_days' => $line['lead_time_days'] ?? null,
                            'line_total_minor' => $lineTotal,
                            'position' => $position,
                        ]);
                    }

                    $quote->subtotal_minor = $subtotal;
                    $quote->total_minor = $subtotal;

                    // Emission eventuelle dans la FOULiE : la transition est
                    // verifiee par la machine a etats, jamais supposee.
                    if ($submit) {
                        $this->quoteStates->assertCanTransitionTo(TradeQuoteStatus::Draft, TradeQuoteStatus::Submitted);
                        $quote->status = TradeQuoteStatus::Submitted;
                        $quote->submitted_at = now();
                    }

                    $quote->save();

                    $this->recordEvent->execute('quote', $quote->id, 'quote.submitted', [
                        'reference' => $quote->reference,
                        'version' => $quote->version,
                        'direction' => $direction->value,
                        'total_minor' => $quote->total_minor,
                        'currency' => $quote->currency,
                        'incoterm' => $quote->incoterm,
                        'submitted' => $submit,
                    ], $employeeId);

                    return $quote;
                });
            } catch (QueryException $exception) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        // Inatteignable : la boucle retourne ou leve (garde PHPStan).
        throw new \RuntimeException('Creation d\'offre impossible apres plusieurs tentatives.');
    }
}
