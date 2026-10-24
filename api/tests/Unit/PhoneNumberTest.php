<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * BC-03 IDENTITE (#8736) — normalisation E.164 du telephone.
 *
 * L'ecart P-5 de l'audit readiness n'etait pas un crash mais une **ambiguite** :
 * le telephone etait un attribut de contact libre, donc deux ecritures du meme
 * numero (`+213 555 12 34 56`, `00213555123456`, `0555 12 34 56`) ne se
 * reconnaissaient pas. Une contrainte d'unicite sur la forme brute n'aurait
 * donc rien garanti. Ces tests verrouillent la propriete qui rend l'unicite
 * utile : **toutes les ecritures d'un meme numero convergent vers une seule
 * chaine** — et un numero qu'on ne peut pas trancher rend `null` plutot qu'un
 * numero faux.
 *
 * Test unitaire pur : aucun boot Laravel, aucune base.
 */
class PhoneNumberTest extends TestCase
{
    public function test_international_form_is_preserved(): void
    {
        $this->assertSame('+213555123456', PhoneNumber::toE164('+213555123456'));
        $this->assertSame('+213555123456', PhoneNumber::toE164('+213 555 12 34 56'));
        $this->assertSame('+213555123456', PhoneNumber::toE164('+213-555-12-34-56'));
    }

    public function test_leading_zeros_international_prefix_is_normalized(): void
    {
        // `00` est l'ecriture internationale usuelle hors Amerique du Nord.
        $this->assertSame('+213555123456', PhoneNumber::toE164('00213555123456'));
    }

    public function test_national_form_uses_the_country_dial_code(): void
    {
        // C'est LA propriete qui rend l'unicite utile : trois ecritures du meme
        // numero algerien convergent vers une seule chaine.
        $this->assertSame('+213555123456', PhoneNumber::toE164('0555 12 34 56', 'DZ'));
        $this->assertSame('+213555123456', PhoneNumber::toE164('+213555123456', 'DZ'));
        $this->assertSame('+213555123456', PhoneNumber::toE164('00213555123456', 'DZ'));
    }

    public function test_national_form_without_country_is_refused_not_guessed(): void
    {
        // `0555123456` pourrait etre algerien, francais ou autre : deviner
        // produirait un numero faux, donc un OTP envoye au mauvais destinataire.
        $this->assertNull(PhoneNumber::toE164('0555123456'));
    }

    public function test_country_outside_the_registry_is_refused(): void
    {
        $this->assertNull(PhoneNumber::toE164('0555123456', 'ZZ'));
        $this->assertNull(PhoneNumber::dialCodeFor('ZZ'));
    }

    public function test_malformed_input_is_refused(): void
    {
        $this->assertNull(PhoneNumber::toE164(''));
        $this->assertNull(PhoneNumber::toE164(null));
        $this->assertNull(PhoneNumber::toE164('pas un numero'));
        $this->assertNull(PhoneNumber::toE164('+213#555'));
        $this->assertNull(PhoneNumber::toE164('12345')); // trop court
    }

    public function test_too_long_number_is_refused(): void
    {
        // E.164 plafonne a 15 chiffres.
        $this->assertNull(PhoneNumber::toE164('+1234567890123456'));
    }

    public function test_is_valid_e164_requires_the_plus_prefix(): void
    {
        $this->assertTrue(PhoneNumber::isValidE164('+213555123456'));
        $this->assertFalse(PhoneNumber::isValidE164('213555123456'));
        $this->assertFalse(PhoneNumber::isValidE164(null));
    }

    public function test_dial_codes_cover_the_platform_countries(): void
    {
        $this->assertSame('213', PhoneNumber::dialCodeFor('DZ'));
        $this->assertSame('33', PhoneNumber::dialCodeFor('FR'));
        $this->assertSame('221', PhoneNumber::dialCodeFor('SN'));
        $this->assertSame('213', PhoneNumber::dialCodeFor('dz')); // insensible a la casse
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function convergenceProvider(): array
    {
        return [
            'DZ international' => ['+213555123456', 'DZ', '+213555123456'],
            'DZ national' => ['0555123456', 'DZ', '+213555123456'],
            'DZ 00-prefix' => ['00213555123456', 'DZ', '+213555123456'],
            'FR national' => ['0612345678', 'FR', '+33612345678'],
            'SN national' => ['771234567', 'SN', '+221771234567'],
            'TR national' => ['05321234567', 'TR', '+905321234567'],
        ];
    }

    #[DataProvider('convergenceProvider')]
    public function test_convergence_table(string $raw, string $country, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toE164($raw, $country));
    }
}
