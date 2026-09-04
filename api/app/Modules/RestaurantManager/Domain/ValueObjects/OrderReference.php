<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Référence unique d'une commande restaurant, format `RST-XXXXXXXX`
 * (8 caractères alphanumériques majuscules). Unique par tenant
 * (contrainte UNIQUE(company_id, reference)).
 *
 * Immuable. (BC-25, RESTO-214, issue #6179)
 */
final class OrderReference
{
    private function __construct(private readonly string $value) {}

    /**
     * Génère une nouvelle référence aléatoire `RST-XXXXXXXX`.
     */
    public static function generate(): self
    {
        return new self('RST-'.strtoupper(Str::random(8)));
    }

    /**
     * Construit une référence depuis une chaîne.
     *
     * @throws InvalidArgumentException si le format attendu n'est pas respecté
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^RST-[A-Z0-9]{8}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid order reference "%s": expected format RST-XXXXXXXX (8 uppercase alphanumeric characters).', $value));
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

    /**
     * Égalité structurelle sur la valeur.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
