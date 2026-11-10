<?php

declare(strict_types=1);

namespace Tests\Unit\Library;

use App\Modules\Library\Domain\Enums\LibraryAccessModel;
use App\Modules\Library\Domain\Support\LibraryPricing;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * BC-38 / BC-17 (#9238) — tarification de la bibliothèque.
 *
 * Ces tests verrouillent les décisions produit documentées dans l'issue :
 * prix TTC, paliers de gros, ventilation exacte, numérotation de facture.
 */
final class LibraryPricingTest extends TestCase
{
    /** @return list<array{min_qty: int, unit_price_minor: int}> */
    private function tiers(): array
    {
        return [
            ['min_qty' => 10, 'unit_price_minor' => 4500],
            ['min_qty' => 50, 'unit_price_minor' => 4000],
            ['min_qty' => 100, 'unit_price_minor' => 3500],
        ];
    }

    public function test_unit_price_falls_back_to_base_below_first_tier(): void
    {
        $this->assertSame(5000, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 1));
        $this->assertSame(5000, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 9));
    }

    public function test_unit_price_applies_the_highest_eligible_tier(): void
    {
        $this->assertSame(4500, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 10));
        $this->assertSame(4500, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 49));
        $this->assertSame(4000, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 50));
        $this->assertSame(3500, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 100));
        $this->assertSame(3500, LibraryPricing::unitPriceMinor(5000, $this->tiers(), 5000));
    }

    public function test_tier_order_does_not_change_the_result(): void
    {
        $shuffled = [
            ['min_qty' => 100, 'unit_price_minor' => 3500],
            ['min_qty' => 10, 'unit_price_minor' => 4500],
            ['min_qty' => 50, 'unit_price_minor' => 4000],
        ];

        $this->assertSame(
            LibraryPricing::unitPriceMinor(5000, $this->tiers(), 60),
            LibraryPricing::unitPriceMinor(5000, $shuffled, 60),
        );
    }

    public function test_no_tiers_means_base_price(): void
    {
        $this->assertSame(5000, LibraryPricing::unitPriceMinor(5000, [], 1000));
    }

    public function test_breakdown_is_exact_and_never_loses_a_cent(): void
    {
        // 19 % sur 10 000 TTC : TVA = 10000 * 0.19 / 1.19 = 1596.638… → 1597
        $b = LibraryPricing::breakdown(10000, 0.19);

        $this->assertSame(10000, $b['total_minor']);
        $this->assertSame(1597, $b['vat_minor']);
        $this->assertSame(8403, $b['net_minor']);
        // L'invariant d'une facture : la somme des lignes égale le total.
        $this->assertSame($b['total_minor'], $b['net_minor'] + $b['vat_minor']);
    }

    public function test_breakdown_with_zero_rate_is_identity(): void
    {
        $b = LibraryPricing::breakdown(12345, 0.0);

        $this->assertSame(0, $b['vat_minor']);
        $this->assertSame(12345, $b['net_minor']);
    }

    public function test_breakdown_invariant_holds_on_many_amounts(): void
    {
        foreach ([1, 7, 99, 100, 1234, 99999, 1000000] as $amount) {
            foreach ([0.0, 0.09, 0.19, 0.20] as $rate) {
                $b = LibraryPricing::breakdown($amount, $rate);
                $this->assertSame(
                    $b['total_minor'],
                    $b['net_minor'] + $b['vat_minor'],
                    "Invariant rompu pour {$amount} à ".($rate * 100).' %.',
                );
            }
        }
    }

    public function test_quote_combines_tier_and_vat(): void
    {
        $q = LibraryPricing::quote(LibraryAccessModel::Purchase, 5000, $this->tiers(), 50, 0.19);

        $this->assertSame(50, $q['quantity']);
        $this->assertSame(4000, $q['unit_price_minor']);
        $this->assertSame(200000, $q['total_minor']);
        $this->assertSame($q['total_minor'], $q['net_minor'] + $q['vat_minor']);
    }

    public function test_quote_refuses_non_purchase_access_models(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LibraryPricing::quote(LibraryAccessModel::Free, 0, [], 1, 0.19);
    }

    public function test_quote_refuses_read_only(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LibraryPricing::quote(LibraryAccessModel::ReadOnly, 5000, [], 1, 0.19);
    }

    public function test_quantity_below_one_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LibraryPricing::unitPriceMinor(5000, [], 0);
    }

    public function test_negative_tier_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LibraryPricing::unitPriceMinor(5000, [['min_qty' => 10, 'unit_price_minor' => -1]], 10);
    }

    public function test_vat_rate_out_of_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LibraryPricing::breakdown(1000, 1.5);
    }

    public function test_invoice_number_is_zero_padded_and_tenant_scoped_by_caller(): void
    {
        $this->assertSame('LIB-2026-000001', LibraryPricing::invoiceNumber(2026, 1));
        $this->assertSame('LIB-2026-123456', LibraryPricing::invoiceNumber(2026, 123456));
    }

    public function test_invoice_sequence_starts_at_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LibraryPricing::invoiceNumber(2026, 0);
    }
}
