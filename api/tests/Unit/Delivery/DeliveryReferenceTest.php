<?php

declare(strict_types=1);

namespace Tests\Unit\Delivery;

use App\Modules\Delivery\Domain\ValueObjects\DeliveryReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * DELIVERY-103 (#6284) — référence de livraison `DLV-YYYY-NNNNNN`.
 */
final class DeliveryReferenceTest extends TestCase
{
    public function test_from_sequence_formats_reference(): void
    {
        self::assertSame('DLV-2026-000123', DeliveryReference::fromSequence(2026, 123)->toString());
        self::assertSame('DLV-2026-000001', DeliveryReference::fromSequence(2026, 1)->toString());
    }

    public function test_from_string_accepts_valid_reference(): void
    {
        $reference = DeliveryReference::fromString('DLV-2026-000123');

        self::assertSame('DLV-2026-000123', $reference->toString());
        self::assertSame('DLV-2026-000123', (string) $reference);
    }

    public function test_from_string_rejects_invalid_reference(): void
    {
        foreach (['DLV-2026-123', 'DLV-26-000123', 'RST-2026-000123', 'dlv-2026-000123', ''] as $invalid) {
            try {
                DeliveryReference::fromString($invalid);
                self::fail(sprintf('Expected InvalidArgumentException for "%s".', $invalid));
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_from_sequence_rejects_invalid_year(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DeliveryReference::fromSequence(1999, 1);
    }

    public function test_from_sequence_rejects_invalid_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DeliveryReference::fromSequence(2026, 1_000_000);
    }
}
