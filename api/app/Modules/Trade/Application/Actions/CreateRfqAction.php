<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeDealDirection;
use App\Modules\Trade\Domain\Enums\TradeDealStatus;
use App\Modules\Trade\Domain\Models\TradeDeal;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Models\TradeRfqLine;
use App\Modules\Trade\Infrastructure\Services\TradeReferenceGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

/**
 * Creation d'une demande de prix (RFQ) — BC-36 TRADE, TRADE-003/#8478.
 *
 * Creation ATOMIQUE : RFQ + lignes dans une seule transaction, plus un dossier
 * de transaction cree a la volee si l'appelant n'en fournit pas. Le dossier est
 * ce qui porte ensuite la conversation, les documents et la timeline — une RFQ
 * n'est donc jamais orpheline.
 *
 * La reference `RFQ-AAAA-NNNNNN` est sequencee PAR TENANT ; une collision sous
 * concurrence est reessayee (patron `RequestRideAction`, BC-35), l'index UNIQUE
 * servant de filet.
 *
 * Les LIGNES acceptent un produit catalogue OU une description LIBRE
 * (`trade_product_id` null) : l'acheteur ne doit jamais etre bloque par
 * l'obligation de creer une fiche produit avant de demander un prix.
 */
final class CreateRfqAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly TradeReferenceGenerator $references,
        private readonly RecordTradeEventAction $recordEvent,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{title: string, destination_country?: string|null, destination_port?: string|null, incoterm?: string|null, budget_minor?: int|null, budget_currency?: string|null, deadline_at?: string|null, notes?: string|null}  $data
     * @param  list<array{description: string, quantity: float|string, unit?: string|null, trade_product_id?: int|null, target_unit_price_minor?: int|null, specs?: array<string, mixed>|null}>  $lines
     */
    public function execute(array $data, array $lines, ?int $employeeId = null, ?int $dealId = null): TradeRfq
    {
        $year = (int) now()->format('Y');

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->db->transaction(function () use ($data, $lines, $employeeId, $dealId, $year): TradeRfq {
                    // Le dossier est cree a la volee si absent : toute RFQ
                    // appartient a une transaction (jamais orpheline).
                    $deal = $dealId !== null
                        ? TradeDeal::query()->whereKey($dealId)->first()
                        : null;

                    if (! $deal instanceof TradeDeal) {
                        /** @var TradeDeal $deal */
                        $deal = TradeDeal::query()->create([
                            'reference' => $this->references->nextDeal($year),
                            'direction' => TradeDealDirection::Import->value,
                            'title' => $data['title'],
                            'status' => TradeDealStatus::Open->value,
                            'currency' => $data['budget_currency'] ?? 'USD',
                            'opened_by_employee_id' => $employeeId,
                        ]);
                    }

                    /** @var TradeRfq $rfq */
                    $rfq = TradeRfq::query()->create([
                        'reference' => $this->references->nextRfq($year),
                        'trade_deal_id' => $deal->id,
                        'title' => $data['title'],
                        'status' => 'draft',
                        'destination_country' => $data['destination_country'] ?? null,
                        'destination_port' => $data['destination_port'] ?? null,
                        'incoterm' => $data['incoterm'] ?? null,
                        'budget_minor' => $data['budget_minor'] ?? null,
                        'budget_currency' => $data['budget_currency'] ?? null,
                        'deadline_at' => $data['deadline_at'] ?? null,
                        'notes' => $data['notes'] ?? null,
                        'created_by_employee_id' => $employeeId,
                    ]);

                    foreach (array_values($lines) as $position => $line) {
                        TradeRfqLine::query()->create([
                            'trade_rfq_id' => $rfq->id,
                            'trade_product_id' => $line['trade_product_id'] ?? null,
                            'description' => $line['description'],
                            'specs' => $line['specs'] ?? null,
                            'quantity' => (string) $line['quantity'],
                            'unit' => $line['unit'] ?? 'unit',
                            'target_unit_price_minor' => $line['target_unit_price_minor'] ?? null,
                            'position' => $position,
                        ]);
                    }

                    $this->recordEvent->execute('rfq', $rfq->id, 'rfq.created', [
                        'reference' => $rfq->reference,
                        'deal_reference' => $deal->reference,
                        'line_count' => count($lines),
                    ], $employeeId);

                    return $rfq;
                });
            } catch (QueryException $exception) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        // Inatteignable : la boucle retourne ou leve (garde PHPStan).
        throw new \RuntimeException('Creation de RFQ impossible apres plusieurs tentatives.');
    }
}
