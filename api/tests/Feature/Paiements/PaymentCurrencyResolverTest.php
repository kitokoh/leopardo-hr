<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Modules\Paiements\Domain\Exceptions\UnsupportedCurrencyException;
use App\Modules\Paiements\Infrastructure\Services\PaymentCurrencyResolver;
use App\Support\SupportedCurrencies;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8743) — allowlist de devises explicite.
 *
 * Le defaut corrige n'etait pas un crash mais un **silence** : une devise
 * absente de la configuration tombait sur `'DZD'` sans le dire, et le solde
 * retourne etait faux. Ces tests verrouillent donc trois choses : une devise
 * inconnue est refusee, aucune constante n'est inventee quand la configuration
 * manque, et l'allowlist reste derivee du registre pays unique — ajouter un
 * pays au registre suffit, sans toucher a ce module.
 *
 * Depuis la correction de la garde d'isolation (#5584), l'allowlist vit dans
 * `App\Support\SupportedCurrencies` et non dans le module Comptabilite : un
 * module ne peut pas importer un autre module.
 */
class PaymentCurrencyResolverTest extends TestCase
{
    private PaymentCurrencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PaymentCurrencyResolver;
    }

    public function test_explicit_supported_currency_is_accepted_and_normalized(): void
    {
        $this->assertSame('DZD', $this->resolver->fromRequest('dzd'));
        $this->assertSame('EUR', $this->resolver->fromRequest(' eur '));
    }

    public function test_malformed_currency_is_rejected(): void
    {
        $this->expectException(UnsupportedCurrencyException::class);

        $this->resolver->fromRequest('EURO');
    }

    public function test_currency_outside_allowlist_is_rejected_instead_of_falling_back(): void
    {
        // 'XTS' est le code ISO reserve aux tests : valide en forme, jamais
        // supporte par le registre. C'est exactement la classe de valeur que
        // `size:3` laissait passer.
        $this->assertFalse(SupportedCurrencies::isSupported('XTS'));

        $this->expectException(UnsupportedCurrencyException::class);

        $this->resolver->fromRequest('XTS');
    }

    public function test_company_currency_is_used_when_request_omits_it(): void
    {
        $this->assertSame('EUR', $this->resolver->resolve(null, 'EUR', 'FR'));
        $this->assertSame('company', $this->resolver->sourceFor(null, 'EUR'));
    }

    public function test_country_default_is_the_last_resort_not_a_hardcoded_constant(): void
    {
        $currency = $this->resolver->resolve(null, null, 'SN');

        $this->assertSame('XOF', $currency);
        $this->assertSame('country_default', $this->resolver->sourceFor(null, null));
    }

    public function test_unsupported_company_currency_falls_back_to_country_not_to_dzd(): void
    {
        // Un tenant francais dont la colonne `currency` contient une valeur
        // invalide doit operer en EUR — surtout pas en DZD.
        $currency = $this->resolver->resolve(null, 'XTS', 'FR');

        $this->assertSame('EUR', $currency);
        $this->assertNotSame('DZD', $currency);
    }

    public function test_explicit_currency_wins_over_company_currency(): void
    {
        $this->assertSame('EUR', $this->resolver->resolve('EUR', 'DZD', 'DZ'));
        $this->assertSame('request', $this->resolver->sourceFor('EUR', 'DZD'));
    }

    public function test_country_outside_registry_is_refused_instead_of_defaulting_to_dzd(): void
    {
        // `CountryDefaults::for()` replie un pays inconnu sur DZ — donc DZD.
        // Ce repli silencieux est precisement le defaut de #8743 : un pays non
        // couvert doit lever, pas operer en dirham algerien.
        $this->expectException(UnsupportedCurrencyException::class);

        $this->resolver->resolve(null, null, 'ZZ');
    }

    public function test_allowlist_is_the_shared_core_registry(): void
    {
        $allowlist = $this->resolver->allowlist();

        // PHPStan voit `list<string>` dans le PHPDoc, donc verifier la nature de
        // liste a l'execution serait tautologique (`function.alreadyNarrowedType`).
        // Ce qui compte ici est le contrat de contenu, pas sa forme.
        $this->assertSame($allowlist, SupportedCurrencies::all());
        $this->assertContains('DZD', $allowlist);
        $this->assertContains('EUR', $allowlist);
        $this->assertNotContains('XTS', $allowlist);
    }

    public function test_exception_messages_name_the_currency(): void
    {
        try {
            $this->resolver->fromRequest('XTS');
            $this->fail('Une devise hors allowlist doit lever.');
        } catch (UnsupportedCurrencyException $exception) {
            $this->assertSame('XTS', $exception->currency);
            $this->assertStringContainsString('XTS', $exception->getMessage());
        }
    }
}
