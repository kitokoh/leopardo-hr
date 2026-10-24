<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BC-38 PAIEMENTS (#8714) — convertisseur monetaire unique.
 *
 * L'ecart P-4 de l'audit readiness n'etait pas un crash mais une **erreur
 * d'arrondi silencieuse** : le depot melange `bigint` (plus petite unite, cote
 * BC-38) et `decimal(x,2)` (~104 colonnes monetaires ailleurs), sans politique
 * de conversion. Ces tests verrouillent donc les quatre proprietes qui rendent
 * la conversion sure : l'arrondi est specifie et **symetrique**, l'exposant est
 * une propriete de la **devise**, le calcul est **exact** (bcmath, jamais de
 * float intermediaire), et une devise hors allowlist est **refusee**.
 *
 * Test unitaire pur : aucun boot Laravel, aucune base. `Money` ne depend que de
 * `SupportedCurrencies`, lui-meme derive du registre pays.
 */
class MoneyTest extends TestCase
{
    public function test_minor_units_round_trip_to_decimal(): void
    {
        $this->assertSame('12.50', Money::fromMinorUnits(1_250, 'DZD')->toDecimal());
        $this->assertSame(1_250, Money::fromDecimal('12.50', 'DZD')->minorUnits());
    }

    public function test_decimal_without_subdivision_currency_has_no_decimals(): void
    {
        // XOF n'a pas de subdivision en circulation : 500 francs, pas 5.00.
        $this->assertSame(0, Money::exponentFor('XOF'));
        $this->assertSame('500', Money::fromMinorUnits(500, 'XOF')->toDecimal());
        $this->assertSame(500, Money::fromDecimal('500', 'XOF')->minorUnits());
    }

    public function test_tunisian_dinar_has_three_decimals(): void
    {
        // Le millime est la 3e decimale du dinar tunisien : supposer 2
        // decimales perdrait 10 % de la valeur sur un montant comme 1.234.
        $this->assertSame(3, Money::exponentFor('TND'));
        $this->assertSame(1_234, Money::fromDecimal('1.234', 'TND')->minorUnits());
        $this->assertSame('1.234', Money::fromMinorUnits(1_234, 'TND')->toDecimal());
    }

    public function test_rounding_is_half_up_away_from_zero(): void
    {
        // 0.005 DZD (demi-centime) arrondit au centime superieur ; en dessous
        // de la moitie, on arrondit vers le bas.
        $this->assertSame(1, Money::fromDecimal('0.005', 'DZD')->minorUnits());
        $this->assertSame(0, Money::fromDecimal('0.004999', 'DZD')->minorUnits());
        $this->assertSame(0, Money::fromDecimal('0.004', 'DZD')->minorUnits());
    }

    public function test_rounding_is_symmetric_so_double_entry_stays_balanced(): void
    {
        // C'est LA propriete qui protege la partie double : arrondir un debit
        // et un credit de meme montant doit donner des valeurs opposees. Un
        // arrondi « demi vers le haut » asymetrique (PHP_ROUND_HALF_UP sur les
        // negatifs) casserait l'invariant SUM(debit) == SUM(credit).
        $debit = Money::fromDecimal('-0.005', 'DZD')->minorUnits();
        $credit = Money::fromDecimal('0.005', 'DZD')->minorUnits();

        $this->assertSame(-1, $debit);
        $this->assertSame(1, $credit);
        $this->assertSame(0, $debit + $credit);
    }

    public function test_float_input_does_not_leak_binary_representation_error(): void
    {
        // 0.1 + 0.2 vaut 0.30000000000000004 en binaire. Un convertisseur qui
        // multiplie un float par 100 produirait 30.000000000000004, donc un
        // arrondi a 30 centimes « par chance » — et 29 sur d'autres valeurs.
        // Ici la representation est fixee avant le calcul.
        $this->assertSame(30, Money::fromDecimal(0.1 + 0.2, 'DZD')->minorUnits());
        $this->assertSame(1_999, Money::fromDecimal(19.99, 'DZD')->minorUnits());
    }

    public function test_arithmetic_keeps_currency_and_exactness(): void
    {
        $a = Money::fromMinorUnits(1_000, 'DZD');
        $b = Money::fromMinorUnits(250, 'DZD');

        $this->assertSame(1_250, $a->plus($b)->minorUnits());
        $this->assertSame(750, $a->minus($b)->minorUnits());
        $this->assertTrue($a->minus($a)->isZero());
        $this->assertTrue($a->equals(Money::fromDecimal('10.00', 'DZD')));
    }

    public function test_adding_different_currencies_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromMinorUnits(100, 'DZD')->plus(Money::fromMinorUnits(100, 'EUR'));
    }

    public function test_currency_outside_allowlist_is_refused_not_converted(): void
    {
        // 'XTS' est le code ISO reserve aux tests : forme valide, jamais
        // supporte. Convertir « au mieux » produirait un montant faux sans le
        // dire — exactement le defaut corrige par #8743.
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.00', 'XTS');
    }

    public function test_malformed_decimal_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('12,50', 'DZD');
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function conversionProvider(): array
    {
        return [
            'DZD exact' => ['12.50', 1_250, 2],
            'DZD arrondi bas' => ['12.504', 1_250, 2],
            'DZD arrondi haut' => ['12.505', 1_251, 2],
            'XOF sans subdivision' => ['500', 500, 0],
            'XOF arrondi' => ['500.5', 501, 0],
            'TND millimes' => ['1.234', 1_234, 3],
            'EUR zero' => ['0.00', 0, 2],
        ];
    }

    #[DataProvider('conversionProvider')]
    public function test_conversion_table(string $decimal, int $expectedMinorUnits, int $expectedExponent): void
    {
        $currency = match ($expectedExponent) {
            0 => 'XOF',
            3 => 'TND',
            default => 'DZD',
        };

        $this->assertSame($expectedExponent, Money::exponentFor($currency));
        $this->assertSame($expectedMinorUnits, Money::fromDecimal($decimal, $currency)->minorUnits());
    }
}
