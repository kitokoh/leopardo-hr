<?php

declare(strict_types=1);

namespace Tests\Unit\Delivery;

use App\Modules\Delivery\Domain\ValueObjects\IdempotencyKey;
use App\Modules\Delivery\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * DELIVERY-103 (#6284) — montants en minor units (COD, commissions, remises)
 * et clés d'idempotence (événements, création par source, règlements).
 */
final class MoneyAndIdempotencyKeyTest extends TestCase
{
    public function test_money_is_immutable_and_arithmetic_is_correct(): void
    {
        $cod = Money::fromMinor(12_000, 'DZD');
        $commission = Money::fromMinor(1_500, 'DZD');

        self::assertSame(12_000, $cod->minor());
        self::assertSame('DZD', $cod->currency());
        self::assertFalse($cod->isZero());
        self::assertFalse($cod->isNegative());
        self::assertTrue(Money::zero('DZD')->isZero());

        $settled = $cod->subtract($commission);
        self::assertSame(10_500, $settled->minor());
        self::assertSame(12_000, $cod->minor(), 'Money must be immutable.');
    }

    public function test_money_rejects_invalid_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinor(100, 'dz');
    }

    public function test_money_rejects_currency_mismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromMinor(100, 'DZD')->add(Money::fromMinor(50, 'XOF'));
    }

    public function test_idempotency_key_generates_uuid_and_validates(): void
    {
        $key = IdempotencyKey::generate();

        self::assertSame(36, strlen($key->toString()));

        $parsed = IdempotencyKey::fromString($key->toString());
        self::assertSame($key->toString(), $parsed->toString());

        $this->expectException(InvalidArgumentException::class);
        IdempotencyKey::fromString('not-a-uuid');
    }
}
