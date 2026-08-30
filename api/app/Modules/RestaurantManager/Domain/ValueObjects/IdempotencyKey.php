<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Clé d'idempotence (UUID v4) portée par les commandes, paiements,
 * remboursements et réservations. Unique par tenant
 * (contrainte UNIQUE(company_id, idempotency_key)).
 *
 * Immuable. (BC-25, RESTO-214, issue #6179)
 */
final class IdempotencyKey
{
    private function __construct(private readonly string $value) {}

    /**
     * Génère une nouvelle clé (UUID v4).
     */
    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }

    /**
     * Construit une clé depuis une chaîne.
     *
     * @throws InvalidArgumentException si la valeur n'est pas un UUID v4
     */
    public static function fromString(string $value): self
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid idempotency key "%s": expected a UUID v4 string.', $value));
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
