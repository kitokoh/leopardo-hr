<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Clé d'idempotence (UUID v4) de la création de course (BC-34 VTC,
 * VTC-02/#8358). Unique par tenant (contrainte
 * UNIQUE(company_id, idempotency_key)) — un double POST avec le même
 * en-tête Idempotency-Key retourne la course existante, jamais de doublon
 * (VTC-03). Immuable — même contrat que BC-26 (Delivery\IdempotencyKey),
 * dupliqué volontairement : aucun import entre verticales (garde #5584).
 */
final class IdempotencyKey
{
    private function __construct(private readonly string $value) {
    }

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
