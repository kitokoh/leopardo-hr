<?php

declare(strict_types=1);

namespace App\Modules\Trade\Infrastructure\Services;

use App\Modules\Trade\Domain\Models\TradeCostEstimate;
use App\Modules\Trade\Domain\Models\TradeDeal;
use App\Modules\Trade\Domain\Models\TradeOrder;
use App\Modules\Trade\Domain\Models\TradeQuote;
use App\Modules\Trade\Domain\Models\TradeRfq;
use App\Modules\Trade\Domain\Models\TradeTransportRequest;
use App\Modules\Trade\Domain\ValueObjects\TradeReference;

/**
 * Generateur de references metier Trade — BC-36 TRADE.
 *
 * Format `PREFIX-AAAA-NNNNNN`, UNIQUE PAR TENANT (jamais globalement). La
 * sequence est comptee dans le tenant courant : le trait BelongsToCompany
 * borne la requete, deux entreprises peuvent donc porter la meme reference
 * sans collision.
 *
 * Le comptage `count() + 1` peut collisionner sous forte concurrence ; les
 * actions appelantes reessaient alors (patron identique a `RequestRideAction`,
 * BC-35) et l'index UNIQUE(company_id, reference) sert de filet.
 */
final class TradeReferenceGenerator
{
    public function nextDeal(int $year): string
    {
        return $this->next('deal', TradeDeal::class, $year);
    }

    public function nextRfq(int $year): string
    {
        return $this->next('rfq', TradeRfq::class, $year);
    }

    public function nextQuote(int $year): string
    {
        return $this->next('quote', TradeQuote::class, $year);
    }

    public function nextOrder(int $year): string
    {
        return $this->next('order', TradeOrder::class, $year);
    }

    public function nextTransportRequest(int $year): string
    {
        return $this->next('transport_request', TradeTransportRequest::class, $year);
    }

    public function nextCostEstimate(int $year): string
    {
        return $this->next('cost_estimate', TradeCostEstimate::class, $year);
    }

    /**
     * @param  class-string  $modelClass
     */
    private function next(string $type, string $modelClass, int $year): string
    {
        $prefix = TradeReference::prefixes()[$type];

        $sequence = $modelClass::query()
            ->where('reference', 'like', sprintf('%s-%d-%%', $prefix, $year))
            ->count() + 1;

        // Le value object VALIDE ce qu'on produit : une incoherence de format
        // serait detectee ici et non silencieusement persistee.
        return TradeReference::fromString(
            sprintf('%s-%d-%06d', $prefix, $year, $sequence),
            $type,
        )->value;
    }
}
