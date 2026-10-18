<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Modules\Trade\Domain\Enums\TradeIncoterm;
use PHPUnit\Framework\TestCase;

/**
 * Carte de repartition des couts par Incoterm (BC-36, TRADE-007/#8482).
 *
 * Enjeu metier : comparer un prix EXW a un prix DDP comme s'ils etaient
 * equivalents est une erreur courante et couteuse. Ce test verrouille la
 * table qui rend cette comparaison refusee.
 */
final class TradeIncotermTest extends TestCase
{
    public function test_buyer_pays_international_freight_for_the_exw_family(): void
    {
        foreach ([TradeIncoterm::EXW, TradeIncoterm::FCA, TradeIncoterm::FAS, TradeIncoterm::FOB] as $term) {
            $this->assertTrue(
                $term->buyerPaysFreight(),
                sprintf('Le fret principal est a la charge de l\'acheteur en %s.', $term->value),
            );
        }
    }

    public function test_seller_pays_international_freight_for_the_delivered_family(): void
    {
        foreach ([TradeIncoterm::CFR, TradeIncoterm::CIF, TradeIncoterm::CPT, TradeIncoterm::CIP, TradeIncoterm::DAP, TradeIncoterm::DPU, TradeIncoterm::DDP] as $term) {
            $this->assertFalse(
                $term->buyerPaysFreight(),
                sprintf('Le fret principal est inclus dans le prix vendeur en %s.', $term->value),
            );
        }
    }

    public function test_only_ddp_puts_import_duties_on_the_seller(): void
    {
        $this->assertFalse(TradeIncoterm::DDP->buyerPaysDuty());

        foreach (TradeIncoterm::cases() as $term) {
            if ($term === TradeIncoterm::DDP) {
                continue;
            }

            $this->assertTrue(
                $term->buyerPaysDuty(),
                sprintf('Les droits d\'import restent a la charge de l\'acheteur en %s.', $term->value),
            );
        }
    }

    public function test_insurance_is_buyer_side_except_for_cif_and_cip(): void
    {
        $this->assertFalse(TradeIncoterm::CIF->buyerPaysInsurance());
        $this->assertFalse(TradeIncoterm::CIP->buyerPaysInsurance());
        $this->assertTrue(TradeIncoterm::CFR->buyerPaysInsurance());
        $this->assertTrue(TradeIncoterm::FOB->buyerPaysInsurance());
    }

    /**
     * Deux offres ne sont comparables que sur le meme Incoterm.
     */
    public function test_only_identical_terms_are_comparable(): void
    {
        $this->assertTrue(TradeIncoterm::FOB->isComparableWith(TradeIncoterm::FOB));
        $this->assertFalse(TradeIncoterm::EXW->isComparableWith(TradeIncoterm::DDP));
        $this->assertFalse(TradeIncoterm::FOB->isComparableWith(TradeIncoterm::CIF));
    }

    public function test_the_eleven_incoterms_2020_are_declared(): void
    {
        $this->assertCount(11, TradeIncoterm::cases());
        $this->assertContains('DDP', TradeIncoterm::values());
        $this->assertContains('EXW', TradeIncoterm::values());
    }
}
