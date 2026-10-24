<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Noyau partage — montant monetaire en plus petite unite (BC-38, #8714).
 *
 * **Pourquoi ici et pas dans un module.** Le depot manipule deux
 * representations du meme montant : `bigint` (plus petite unite) dans le BC-38
 * Paiements, et `decimal(x,2)` sur ~104 colonnes monetaires ailleurs (paie,
 * avances, factures, comptabilite). La conversion entre les deux est le point
 * exact ou une erreur d'arrondi devient silencieuse. La garde d'isolation des
 * modules (`ARCHITECTURE.md` §2, #5584) interdit a un module d'importer un
 * autre module : un convertisseur unique ne peut donc pas vivre dans
 * `Modules/Paiements` et etre consomme par `Modules/Payroll`. `App\Support` est
 * le noyau partage, precisement destine a ce que plusieurs BC connaissent —
 * meme raison que `SupportedCurrencies` (#8743).
 *
 * **Politique de conversion (ecart P-4 de l'audit readiness).**
 *   1. *Sens* : `fromDecimal()` va du monde `decimal` vers le monde `bigint` ;
 *      `toDecimal()` fait le retour. Aucune conversion implicite ailleurs.
 *   2. *Arrondi* : **demi vers l'exterieur** (`half-up away from zero`), a
 *      l'exposant de la devise. `0.005` en DZD (exposant 2) devient `1`
 *      centime, `-0.005` devient `-1`. C'est l'arrondi commercial usuel, et il
 *      est **symetrique** : arrondir un debit et un credit de meme montant
 *      donne des valeurs opposees, donc la partie double reste equilibree.
 *   3. *Devise* : l'exposant est une propriete de la devise, pas du montant.
 *      XOF/XAF n'ont pas de subdivision (exposant 0), TND en a trois
 *      (millimes). Une devise hors allowlist est refusee — jamais convertie
 *      « au mieux ».
 *   4. *Exactitude* : le calcul passe par `bcmath`, jamais par un `float`
 *      intermediaire. Un `float` ne represente pas exactement `0.1`, et
 *      l'erreur se propage a chaque frontiere d'integration.
 *
 * @see docs/adr/BC-38-005-pont-comptable-payroll-paiements.md
 */
final class Money
{
    /**
     * Exposant (nombre de decimales) des devises qui ne suivent pas la
     * convention ISO 4217 par defaut de 2 decimales.
     *
     * @var array<string, int>
     */
    private const EXPONENTS = [
        // Francs CFA : pas de subdivision en circulation.
        'XOF' => 0,
        'XAF' => 0,
        // Dinar tunisien : le millime est la 3e decimale.
        'TND' => 3,
    ];

    /** Exposant ISO 4217 par defaut. */
    private const DEFAULT_EXPONENT = 2;

    private function __construct(
        private readonly int $minorUnits,
        private readonly string $currency,
    ) {}

    /**
     * Construit un montant depuis sa plus petite unite (le monde BC-38).
     */
    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        return new self($minorUnits, self::assertSupported($currency));
    }

    /**
     * Construit un montant depuis une valeur decimale (le monde `decimal(x,2)`).
     *
     * L'arrondi est applique a l'exposant de la devise, en demi vers
     * l'exterieur. Voir la politique de conversion dans le docblock de classe.
     */
    public static function fromDecimal(string|int|float $amount, string $currency): self
    {
        $currency = self::assertSupported($currency);
        $exponent = self::exponentFor($currency);
        $normalized = self::normalizeDecimal($amount);

        // Mise a l'echelle exacte, puis arrondi demi-vers-l'exterieur :
        // `bcadd(..., 0)` tronque vers zero, donc ajouter 0.5 (ou -0.5 pour un
        // negatif) avant troncature realise l'arrondi commercial, de facon
        // symetrique entre debit et credit.
        $scaled = bcmul($normalized, bcpow('10', (string) $exponent), 10);
        $half = str_starts_with($scaled, '-') ? '-0.5' : '0.5';
        $rounded = bcadd($scaled, $half, 0);

        return new self((int) $rounded, $currency);
    }

    /**
     * Plus petite unite de la devise — la representation stockee par le BC-38.
     */
    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Representation decimale, avec exactement l'exposant de la devise.
     *
     * `1250` en DZD rend `"12.50"` ; `500` en XOF rend `"500"`.
     */
    public function toDecimal(): string
    {
        return bcdiv(
            (string) $this->minorUnits,
            bcpow('10', (string) self::exponentFor($this->currency)),
            self::exponentFor($this->currency),
        );
    }

    /**
     * Exposant (nombre de decimales) d'une devise supportee.
     *
     * @throws InvalidArgumentException si la devise n'est pas dans l'allowlist
     */
    public static function exponentFor(string $currency): int
    {
        $currency = self::assertSupported($currency);

        return self::EXPONENTS[$currency] ?? self::DEFAULT_EXPONENT;
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && $this->minorUnits === $other->minorUnits;
    }

    /**
     * Normalise une valeur decimale en chaine exploitable par `bcmath`.
     *
     * Un `float` est converti sans notation scientifique : `sprintf('%.10F')`
     * fixe la representation, puis les zeros de queue sont retires pour que
     * `bcmath` recoive une chaine canonique.
     */
    private static function normalizeDecimal(string|int|float $amount): string
    {
        if (is_float($amount)) {
            $amount = rtrim(rtrim(sprintf('%.10F', $amount), '0'), '.');
        }

        $value = trim((string) $amount);

        if ($value === '' || preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Montant decimal invalide : "%s".',
                $value,
            ));
        }

        return $value;
    }

    /**
     * Une devise hors allowlist est refusee : convertir « au mieux » une devise
     * inconnue produirait un montant faux sans le dire — exactement le defaut
     * corrige par #8743.
     */
    private static function assertSupported(string $currency): string
    {
        $normalized = SupportedCurrencies::normalize($currency);

        if ($normalized === null || ! SupportedCurrencies::isSupported($normalized)) {
            throw new InvalidArgumentException(sprintf(
                'Devise non supportee pour une conversion monetaire : "%s".',
                $currency,
            ));
        }

        return $normalized;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Addition de devises differentes : %s et %s.',
                $this->currency,
                $other->currency,
            ));
        }
    }
}
