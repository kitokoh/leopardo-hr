<?php

declare(strict_types=1);

namespace App\Shared\Geo;

use InvalidArgumentException;

/**
 * GEO-03 (#8352, BC-33 GEO) — distance en mètres (entier non signé).
 *
 * Value object immuable partagé (même raison que GeoPoint : garde #5584) —
 * évite la confusion d'unités (mètres vs kilomètres) dans les signatures
 * des verticales consommatrices et centralise les conversions d'affichage.
 */
final class Distance
{
    private function __construct(
        public readonly int $meters,
    ) {
        if ($meters < 0) {
            throw new InvalidArgumentException("Une distance ne peut pas être négative ({$meters} m).");
        }
    }

    public static function fromMeters(int $meters): self
    {
        return new self($meters);
    }

    public static function fromFloatMeters(float $meters): self
    {
        return new self((int) round($meters));
    }

    public function meters(): int
    {
        return $this->meters;
    }

    public function kilometers(): float
    {
        return $this->meters / 1000.0;
    }
}
