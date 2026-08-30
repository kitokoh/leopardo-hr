<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Référence d'une livraison (BC-26 DELIVERY, DELIVERY-103/#6284).
 *
 * Format `DLV-YYYY-NNNNNN` (ex. DLV-2026-000123), unique par tenant
 * (contrainte UNIQUE(company_id, reference)). Immuable.
 */
final class DeliveryReference
{
    private const PATTERN = '/^DLV-\d{4}-\d{6}$/';

    private function __construct(private readonly string $value) {}

    /**
     * Génère la prochaine référence pour une année et un séquenceur.
     */
    public static function fromSequence(int $year, int $sequence): self
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException(sprintf('Invalid year "%d" for delivery reference.', $year));
        }

        if ($sequence < 0 || $sequence > 999_999) {
            throw new InvalidArgumentException(sprintf('Invalid sequence "%d" for delivery reference.', $sequence));
        }

        return new self(sprintf('DLV-%04d-%06d', $year, $sequence));
    }

    /**
     * Construit une référence depuis une chaîne.
     *
     * @throws InvalidArgumentException si le format n'est pas DLV-YYYY-NNNNNN
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid delivery reference "%s": expected DLV-YYYY-NNNNNN.', $value));
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
