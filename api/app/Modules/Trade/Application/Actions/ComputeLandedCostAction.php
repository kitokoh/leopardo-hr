<?php

declare(strict_types=1);

namespace App\Modules\Trade\Application\Actions;

use App\Modules\Trade\Domain\Enums\TradeCostLineType;
use App\Modules\Trade\Domain\Enums\TradeCostSource;
use App\Modules\Trade\Domain\Enums\TradeIncoterm;
use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use App\Modules\Trade\Domain\Models\TradeCostEstimateLine;
use App\Modules\Trade\Infrastructure\Services\TradeLandedCostCalculator;
use App\Modules\Trade\Infrastructure\Services\TradeReferenceGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

/**
 * Calcul du cout total rendu — BC-36 TRADE, TRADE-007/#8482.
 *
 * Persiste une estimation et ses postes, puis delegate TOUT le calcul a
 * {@see TradeLandedCostCalculator} (service pur). L'action ne fait que de la
 * persistance : aucune regle de calcul ne vit ici, ce qui garantit qu'un test
 * unitaire du service suffit a valider la logique de chiffrage.
 *
 * Chaque poste recoit une PROVENANCE (`source`). Le statut de l'estimation est
 * `incomplete` des qu'un poste est indisponible, non convertible faute de taux,
 * ou qu'un poste attendu selon l'Incoterm manque — l'utilisateur ne peut donc
 * pas confondre un total partiel avec un prix final.
 */
final class ComputeLandedCostAction
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly TradeReferenceGenerator $references,
        private readonly TradeLandedCostCalculator $calculator,
        private readonly RecordTradeEventAction $recordEvent,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * @param  array{goods_value_minor: int, goods_currency: string, display_currency: string, origin_country?: string|null, destination_country?: string|null, incoterm?: string|null, trade_deal_id?: int|null, trade_quote_id?: int|null, trade_order_id?: int|null, notes?: string|null}  $data
     * @param  list<array{type: string, amount_minor: int, currency: string, source?: string|null, label?: string|null, note?: string|null}>  $lines
     * @param  array{rate: float, date: string, source: string}|null  $fx
     */
    public function execute(array $data, array $lines, ?array $fx = null, ?int $employeeId = null): TradeCostEstimate
    {
        $year = (int) now()->format('Y');

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->db->transaction(function () use ($data, $lines, $fx, $employeeId, $year): TradeCostEstimate {
                    /** @var TradeCostEstimate $estimate */
                    $estimate = TradeCostEstimate::query()->create([
                        'reference' => $this->references->nextCostEstimate($year),
                        'trade_deal_id' => $data['trade_deal_id'] ?? null,
                        'trade_quote_id' => $data['trade_quote_id'] ?? null,
                        'trade_order_id' => $data['trade_order_id'] ?? null,
                        'origin_country' => $data['origin_country'] ?? null,
                        'destination_country' => $data['destination_country'] ?? null,
                        'incoterm' => $data['incoterm'] ?? null,
                        'goods_value_minor' => $data['goods_value_minor'],
                        'goods_currency' => $data['goods_currency'],
                        'display_currency' => $data['display_currency'],
                        // Le taux n'est jamais deduit : soit il est fourni et
                        // trace, soit il reste null et les conversions sont
                        // signalees comme impossibles.
                        'fx_rate' => $fx['rate'] ?? null,
                        'fx_rate_date' => $fx['date'] ?? null,
                        'fx_source' => $fx['source'] ?? null,
                        'status' => 'draft',
                        'notes' => $data['notes'] ?? null,
                        'created_by_employee_id' => $employeeId,
                    ]);

                    // Le prix d'achat est un poste comme un autre, ajoute en
                    // tete : il doit apparaitre dans la decomposition affichee.
                    $position = 0;

                    TradeCostEstimateLine::query()->create([
                        'trade_cost_estimate_id' => $estimate->id,
                        'type' => TradeCostLineType::PurchasePrice->value,
                        'amount_minor' => $data['goods_value_minor'],
                        'currency' => $data['goods_currency'],
                        'source' => TradeCostSource::UserInput->value,
                        'position' => $position++,
                    ]);

                    foreach ($lines as $line) {
                        TradeCostEstimateLine::query()->create([
                            'trade_cost_estimate_id' => $estimate->id,
                            'type' => $line['type'],
                            'label' => $line['label'] ?? null,
                            'amount_minor' => $line['amount_minor'],
                            'currency' => $line['currency'],
                            'source' => $line['source'] ?? TradeCostSource::UserInput->value,
                            'note' => $line['note'] ?? null,
                            'position' => $position++,
                        ]);
                    }

                    $estimate->load('lines');

                    $incoterm = $estimate->incoterm !== null
                        ? TradeIncoterm::tryFrom($estimate->incoterm)
                        : null;

                    $result = $this->calculator->compute(
                        array_values($estimate->lines->map(static fn (TradeCostEstimateLine $line): array => [
                            'type' => $line->type->value,
                            'source' => $line->source->value,
                            'amount_minor' => $line->amount_minor,
                            'currency' => $line->currency,
                        ])->all()),
                        $incoterm,
                        $estimate->fx_rate !== null ? (float) $estimate->fx_rate : null,
                        $estimate->display_currency,
                    );

                    // Les lignes converties portent leur montant converti :
                    // la tracabilite du chiffre affiche est complete.
                    if ($estimate->fx_rate !== null) {
                        $rate = (float) $estimate->fx_rate;

                        foreach ($estimate->lines as $line) {
                            if ($line->currency === $estimate->display_currency) {
                                $line->converted_amount_minor = $line->amount_minor;
                            } elseif ($line->source->countsTowardTotal()) {
                                $line->converted_amount_minor = (int) round($line->amount_minor * $rate);
                            }

                            $line->save();
                        }
                    }

                    $estimate->total_minor = $result['total_minor'];
                    $estimate->status = $result['incomplete'] ? 'incomplete' : 'complete';
                    $estimate->save();

                    $this->recordEvent->execute('cost_estimate', $estimate->id, 'cost.computed', [
                        'reference' => $estimate->reference,
                        'total_minor' => $result['total_minor'],
                        'incomplete' => $result['incomplete'],
                        'unavailable_types' => $result['unavailable_types'],
                        'missing_expected_types' => $result['missing_expected_types'],
                    ], $employeeId);

                    return $estimate;
                });
            } catch (QueryException $exception) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $exception;
                }
            }
        }

        // Inatteignable : la boucle retourne ou leve (garde PHPStan).
        throw new \RuntimeException('Calcul de cout impossible apres plusieurs tentatives.');
    }
}
