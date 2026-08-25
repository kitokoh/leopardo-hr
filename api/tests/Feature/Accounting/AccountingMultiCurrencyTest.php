<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Application\Actions\AccountingCurrencyResolver;
use App\Modules\Accounting\Application\Actions\DocumentCurrencyConverter;
use App\Modules\Accounting\Application\DTOs\ConvertedTotals;
use App\Modules\Accounting\Domain\Contracts\CurrencyRateProviderInterface;
use App\Modules\Accounting\Domain\Exceptions\CurrencyRateUnavailableException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Infrastructure\Services\ManualCurrencyRateProvider;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5270 — multi-devises + taux de change (facturation).
 *
 * Couvre : devise par contact (défaut entreprise : settings → pays, devise
 * explicite, 422 hors registre, pas de surcharge au PUT), endpoint
 * POST /accounting/currency/convert (golden arrondis half-up, identité,
 * taux requis entre devises différentes, RBAC) et le convertisseur
 * (totaux devise document + devise de référence, provider externe,
 * exception sans taux).
 */
class AccountingMultiCurrencyTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $manager;
    }

    private function ordinaryEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        return $employee;
    }

    private function bindCompany(Company $company): void
    {
        app()->instance('current_company', $company);
    }

    // ───────────────────────── Devise par contact ─────────────────────────

    public function test_contact_store_defaults_currency_from_settings(): void
    {
        $this->bindCompany($this->companyA);

        AccountingSettings::query()->create([
            'company_id' => $this->companyA->id,
            'currency' => 'EUR',
        ]);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
            'name' => 'Client sans devise',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.currency', 'EUR');
    }

    public function test_contact_store_defaults_currency_from_company_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->bindCompany($company);

        Sanctum::actingAs($this->manager($company));

        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'supplier',
            'name' => 'Fournisseur sans devise',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.currency', 'MAD');
    }

    public function test_contact_store_accepts_explicit_supported_currency(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
            'name' => 'Client Europe',
            'currency' => 'EUR',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.currency', 'EUR');
    }

    public function test_contact_store_rejects_unsupported_currency(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
            'name' => 'Client devise inconnue',
            'currency' => 'ZZZ',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('currency');
    }

    public function test_contact_update_without_currency_keeps_existing(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $created = $this->postJson('/api/v1/accounting/contacts', [
            'type' => 'customer',
            'name' => 'Client devise EUR',
            'currency' => 'EUR',
        ])->assertStatus(201)->json('data');

        $response = $this->putJson('/api/v1/accounting/contacts/'.$created['id'], [
            'name' => 'Client renommé',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.currency', 'EUR');
        $response->assertJsonPath('data.name', 'Client renommé');
    }

    // ───────────────────────── Endpoint de conversion ─────────────────────

    public function test_convert_endpoint_golden_manual_rate(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 100,
            'from_currency' => 'EUR',
            'to_currency' => 'DZD',
            'rate' => 1.05,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.amount', 100)
            ->assertJsonPath('data.from_currency', 'EUR')
            ->assertJsonPath('data.to_currency', 'DZD')
            ->assertJsonPath('data.rate', 1.05)
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.converted_amount', 105)
            ->assertJsonPath('data.rounding', 'half_up')
            ->assertJsonPath('data.decimals', 2);
    }

    public function test_convert_endpoint_rounds_half_up(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 19.99,
            'from_currency' => 'EUR',
            'to_currency' => 'DZD',
            'rate' => 1.007,
        ]);

        // 19,99 × 1,007 = 20,12993 → half-up 2 décimales = 20,13 (pas 20,12).
        $response->assertStatus(200)
            ->assertJsonPath('data.converted_amount', 20.13);
    }

    public function test_convert_endpoint_identity_when_same_currency(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 250,
            'from_currency' => 'DZD',
            'to_currency' => 'DZD',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rate', 1)
            ->assertJsonPath('data.source', 'identity')
            ->assertJsonPath('data.converted_amount', 250);
    }

    public function test_convert_endpoint_requires_rate_when_currencies_differ(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 100,
            'from_currency' => 'EUR',
            'to_currency' => 'DZD',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rate');
    }

    public function test_convert_endpoint_rejects_invalid_rate(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 100,
            'from_currency' => 'EUR',
            'to_currency' => 'DZD',
            'rate' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('rate');
    }

    public function test_convert_endpoint_rejects_unsupported_currency(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 100,
            'from_currency' => 'ZZZ',
            'to_currency' => 'DZD',
            'rate' => 1.05,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('from_currency');
    }

    public function test_convert_endpoint_rejects_ordinary_employee(): void
    {
        $this->bindCompany($this->companyA);

        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $response = $this->postJson('/api/v1/accounting/currency/convert', [
            'amount' => 100,
            'from_currency' => 'EUR',
            'to_currency' => 'DZD',
            'rate' => 1.05,
        ]);

        $response->assertStatus(403);
    }

    // ───────────────────────── Convertisseur (service) ────────────────────

    public function test_converter_convert_totals_document_and_reference_currency(): void
    {
        $document = $this->document('EUR', 1000.0, 190.0, 1190.0);

        $result = (new DocumentCurrencyConverter)->convertTotals($document, 'DZD', 1.05);

        $this->assertInstanceOf(ConvertedTotals::class, $result);
        $this->assertSame('EUR', $result->documentCurrency);
        $this->assertSame('DZD', $result->referenceCurrency);
        $this->assertSame(1.05, $result->rate);
        $this->assertSame('manual', $result->source);
        // Devise du document : montants d'origine.
        $this->assertSame(1000.0, $result->subtotalHt);
        $this->assertSame(190.0, $result->taxAmount);
        $this->assertSame(1190.0, $result->totalTtc);
        // Devise de référence : TVA calculée en devise document puis convertie.
        $this->assertSame(1050.0, $result->subtotalHtConverted);
        $this->assertSame(199.5, $result->taxAmountConverted);
        $this->assertSame(1249.5, $result->totalTtcConverted);
    }

    public function test_converter_totals_identity_when_document_in_reference_currency(): void
    {
        $document = $this->document('DZD', 5000.0, 950.0, 5950.0);

        $result = (new DocumentCurrencyConverter)->convertTotals($document, 'DZD');

        $this->assertSame(1.0, $result->rate);
        $this->assertSame('identity', $result->source);
        $this->assertSame(5000.0, $result->subtotalHtConverted);
        $this->assertSame(5950.0, $result->totalTtcConverted);
    }

    public function test_converter_uses_external_provider(): void
    {
        $provider = new class implements CurrencyRateProviderInterface
        {
            public function rate(string $from, string $to): float
            {
                return 2.5;
            }

            public function source(): string
            {
                return 'external_fake';
            }

            public function supports(string $from, string $to): bool
            {
                return $from === 'USD' && $to === 'DZD';
            }
        };

        $result = (new DocumentCurrencyConverter($provider))->convertAmount(40.0, 'USD', 'DZD');

        $this->assertSame(2.5, $result->rate);
        $this->assertSame('external_fake', $result->source);
        $this->assertSame(100.0, $result->convertedAmount);
    }

    public function test_converter_uses_manual_provider_implementation(): void
    {
        $result = (new DocumentCurrencyConverter(new ManualCurrencyRateProvider(1.25)))
            ->convertAmount(80.0, 'EUR', 'USD');

        $this->assertSame('manual', $result->source);
        $this->assertSame(100.0, $result->convertedAmount);
    }

    public function test_converter_throws_when_no_rate_resolvable(): void
    {
        $this->expectException(CurrencyRateUnavailableException::class);

        (new DocumentCurrencyConverter)->convertAmount(100.0, 'EUR', 'DZD');
    }

    public function test_converter_throws_on_invalid_currency_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new DocumentCurrencyConverter)->convertAmount(100.0, 'ZZZZ', 'DZD', 1.05);
    }

    public function test_resolver_for_company_prefers_settings_then_country(): void
    {
        $resolver = new AccountingCurrencyResolver;

        // Settings persistés → devise des settings.
        $settings = new AccountingSettings(['currency' => 'TRY']);
        $this->assertSame('TRY', $resolver->forCompany('DZ', $settings));

        // Pas de settings → devise du pays.
        $this->assertSame('DZD', $resolver->forCompany('DZ', null));
        $this->assertSame('MAD', $resolver->forCompany('MA', null));
    }

    /**
     * Document comptable de test (champs totaux de la migration #5221).
     */
    private function document(string $currency, float $subtotal, float $tax, float $total): AccountingDocument
    {
        $document = new AccountingDocument([
            'company_id' => $this->companyA->id,
            'type' => 'invoice',
            'number' => 'FAC-2026-0001',
            'status' => 'draft',
            'currency' => $currency,
            'subtotal_ht' => $subtotal,
            'tax_amount' => $tax,
            'total_ttc' => $total,
            'issue_date' => now()->toDateString(),
        ]);

        return $document;
    }
}
