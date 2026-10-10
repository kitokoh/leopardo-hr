<?php

declare(strict_types=1);

namespace App\Modules\Vtc\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Référence lisible d'une course VTC (BC-34 VTC, VTC-02/#8358).
 *
 * Format `VTC-YYYY-NNNNNN` (ex. VTC-2026-000123), unique par tenant
 * (contrainte UNIQUE(company_id, reference)). Immuable — même pattern que
 * DeliveryReference (BC-26).
 */
final class RideReference
{
    private const PATTERN = '/^VTC-\d{4}-\d{6}$/';

    private function __construct(private readonly string $value) {
    }

    /**
     * Génère la référence pour une année et un séquenceur.
     */
    public static function fromSequence(int $year, int $sequence): self
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException(sprintf('Invalid year "%d" for ride reference.', $year));
        }

        if ($sequence < 0 || $sequence > 999_999) {
            throw new InvalidArgumentException(sprintf('Invalid sequence "%d" for ride reference.', $sequence));
        }

        return new self(sprintf('VTC-%04d-%06d', $year, $sequence));
    }

    /**
     * Construit une référence depuis une chaîne.
     *
     * @throws InvalidArgumentException si le format n'est pas VTC-YYYY-NNNNNN
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid ride reference "%s": expected VTC-YYYY-NNNNNN.', $value));
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
