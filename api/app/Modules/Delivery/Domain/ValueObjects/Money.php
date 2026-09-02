<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Montant monétaire exprimé en unités mineures entières (aucun flottant en
 * base — les montants COD, commissions et remises sont stockés en minor
 * units). Immuable : chaque opération renvoie une nouvelle instance.
 *
 * (BC-26 DELIVERY, DELIVERY-103/#6284 — même contrat que BC-25 RESTAURANT)
 */
final class Money
{
    private function __construct(
        private readonly int $minor,
        private readonly string $currency,
    ) {}

    /**
     * @throws InvalidArgumentException si le code devise n'est pas ISO 4217
     */
    public static function fromMinor(int $minor, string $currency): self
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid currency code "%s": expected ISO 4217 format (3 uppercase letters).', $currency));
        }

        return new self($minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return self::fromMinor(0, $currency);
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Currency mismatch: cannot operate on "%s" and "%s".',
                $this->currency,
                $other->currency,
            ));
        }
    }
}
