<?php

declare(strict_types=1);

namespace Tests\Unit\Trade;

use App\Modules\Trade\Domain\ValueObjects\TradeReference;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reference metier Trade — BC-36, format `PREFIX-AAAA-NNNNNN`.
 */
final class TradeReferenceTest extends TestCase
{
    /**
     * @dataProvider validReferences
     */
    public function test_it_accepts_the_reference_of_each_object_type(string $value, string $type, string $prefix): void
    {
        $reference = TradeReference::fromString($value, $type);

        $this->assertSame($prefix, $reference->prefix());
        $this->assertSame($value, (string) $reference);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function validReferences(): array
    {
        return [
            'deal' => ['TRD-2026-000123', 'deal', 'TRD'],
            'rfq' => ['RFQ-2026-000001', 'rfq', 'RFQ'],
            'quote' => ['QTE-2026-004512', 'quote', 'QTE'],
            'order' => ['ORD-2026-000007', 'order', 'ORD'],
            'transport' => ['TTR-2026-000099', 'transport_request', 'TTR'],
            'cost' => ['LCE-2026-000042', 'cost_estimate', 'LCE'],
        ];
    }

    public function test_it_normalises_case_and_surrounding_spaces(): void
    {
        $reference = TradeReference::fromString('  rfq-2026-000123  ', 'rfq');

        $this->assertSame('RFQ-2026-000123', (string) $reference);
        $this->assertSame(2026, $reference->year());
    }

    public function test_it_accepts_sequences_beyond_six_digits(): void
    {
        // Une entreprise a fort volume ne doit pas se retrouver bloquee.
        $reference = TradeReference::fromString('TRD-2026-1234567', 'deal');

        $this->assertSame('TRD-2026-1234567', (string) $reference);
    }

    /**
     * @dataProvider invalidReferences
     */
    public function test_it_refuses_a_malformed_reference(string $value, string $type): void
    {
        $this->expectException(InvalidArgumentException::class);

        TradeReference::fromString($value, $type);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidReferences(): array
    {
        return [
            'wrong prefix' => ['ORD-2026-000123', 'rfq'],
            'missing year' => ['RFQ-000123', 'rfq'],
            'short year' => ['RFQ-26-000123', 'rfq'],
            'short sequence' => ['RFQ-2026-123', 'rfq'],
            'non numeric sequence' => ['RFQ-2026-ABC123', 'rfq'],
            'lowercase separator' => ['RFQ_2026_000123', 'rfq'],
        ];
    }

    public function test_it_refuses_an_unknown_object_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TradeReference::fromString('RFQ-2026-000123', 'not_a_trade_object');
    }
}
