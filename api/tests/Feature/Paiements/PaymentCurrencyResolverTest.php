<?php

declare(strict_types=1);

namespace Tests\Feature\Paiements;

use App\Modules\Accounting\Infrastructure\Services\AccountingCurrencies;
use App\Modules\Paiements\Domain\Exceptions\UnsupportedCurrencyException;
use App\Modules\Paiements\Infrastructure\Services\PaymentCurrencyResolver;
use Tests\TestCase;

/**
 * BC-38 PAIEMENTS (#8743) — allowlist de devises explicite.
 *
 * Le defaut corrige n'etait pas un crash mais un **silence** : une devise
 * absente de la configuration tombait sur `'DZD'` sans le dire, et le solde
 * retourne etait faux. Ces tests verrouillent donc trois choses : une devise
 * inconnue est refusee, aucune constante n'est inventee quand la configuration
 * manque, et l'allowlist reste derivee du registre unique `AccountingCurrencies`
 * (ajouter une devise au registre suffit, sans toucher a ce module).
 */
class PaymentCurrencyResolverTest extends TestCase
{
    private PaymentCurrencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PaymentCurrencyResolver();
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
        $this->assertFalse(AccountingCurrencies::isSupported('XTS'));

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

    public function test_allowlist_is_the_shared_accounting_registry(): void
    {
        $this->assertSame(AccountingCurrencies::supported(), $this->resolver->allowlist());
        $this->assertContains('DZD', $this->resolver->allowlist());
        $this->assertContains('EUR', $this->resolver->allowlist());
        $this->assertNotContains('XTS', $this->resolver->allowlist());
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
