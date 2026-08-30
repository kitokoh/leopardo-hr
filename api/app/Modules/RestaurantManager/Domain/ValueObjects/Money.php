<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Montant monétaire exprimé en unités mineures entières (spec §1.3 règle 5 :
 * aucun flottant en base, les montants sont stockés en minor units).
 *
 * Immuable : chaque opération renvoie une nouvelle instance.
 *
 * (BC-25, RESTO-214, issue #6179)
 */
final class Money
{
    private function __construct(
        private readonly int $minor,
        private readonly string $currency,
    ) {}

    /**
     * Construit un montant en unités mineures.
     *
     * @throws InvalidArgumentException si le code devise n'est pas au format ISO 4217 ([A-Z]{3})
     */
    public static function fromMinor(int $minor, string $currency): self
    {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid currency code "%s": expected ISO 4217 format (3 uppercase letters).', $currency));
        }

        return new self($minor, $currency);
    }

    /**
     * Montant nul dans la devise donnée.
     */
    public static function zero(string $currency): self
    {
        return self::fromMinor(0, $currency);
    }

    /**
     * Montant en unités mineures (ex. centimes).
     */
    public function minor(): int
    {
        return $this->minor;
    }

    /**
     * Code devise ISO 4217 (ex. "XOF").
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * True si le montant est nul.
     */
    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    /**
     * Égalité structurelle : même devise ET même montant.
     */
    public function equals(Money $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /**
     * Somme de deux montants (même devise requise).
     *
     * @throws InvalidArgumentException si les devises diffèrent
     */
    public function add(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    /**
     * Différence de deux montants (même devise requise).
     *
     * @throws InvalidArgumentException si les devises diffèrent
     */
    public function subtract(Money $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * Représentation sérialisable du montant.
     *
     * @return array{minor: int, currency: string}
     */
    public function toArray(): array
    {
        return [
            'minor' => $this->minor,
            'currency' => $this->currency,
        ];
    }

    /**
     * @throws InvalidArgumentException si la devise diffère
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf('Cannot combine money of different currencies ("%s" vs "%s").', $this->currency, $other->currency));
        }
    }
}
