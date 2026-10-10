<?php

declare(strict_types=1);

namespace Tests\Unit\Vtc;

use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\ValueObjects\IdempotencyKey;
use App\Modules\Vtc\Domain\ValueObjects\RideReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * VTC-02 (#8358, BC-34 VTC) — value objects et enums du domaine.
 */
class VtcValueObjectsTest extends TestCase
{
    public function test_ride_reference_format(): void
    {
        $reference = RideReference::fromSequence(2026, 123);

        self::assertSame('VTC-2026-000123', $reference->toString());
        self::assertSame('VTC-2026-000123', RideReference::fromString('VTC-2026-000123')->toString());
    }

    public function test_ride_reference_rejects_invalid_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RideReference::fromString('DLV-2026-000123');
    }

    public function test_ride_reference_rejects_out_of_range_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RideReference::fromSequence(2026, 1_000_000);
    }

    public function test_idempotency_key_accepts_uuid_v4_only(): void
    {
        $generated = IdempotencyKey::generate();

        self::assertSame($generated->toString(), IdempotencyKey::fromString($generated->toString())->toString());

        $this->expectException(InvalidArgumentException::class);
        IdempotencyKey::fromString('not-a-uuid');
    }

    public function test_enums_expose_expected_values(): void
    {
        self::assertSame(
            ['requested', 'dispatching', 'accepted', 'arrived', 'in_progress', 'completed', 'expired', 'cancelled'],
            VtcRideStatus::values()
        );

        self::assertSame(['offline', 'available', 'busy', 'suspended'], VtcDriverStatus::values());
    }
}
