<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Clé d'idempotence (UUID v4) portée par les événements de tracking, la
 * création par source et les règlements COD. Unique par tenant
 * (contrainte UNIQUE(company_id, idempotency_key)) — un rejeu ne duplique
 * jamais une livraison, un événement ou un règlement.
 *
 * Immuable. (BC-26 DELIVERY, DELIVERY-103/#6284 — même contrat que BC-25)
 */
final class IdempotencyKey
{
    private function __construct(private readonly string $value) {}

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }

    /**
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
