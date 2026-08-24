<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\CompanyCreated;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5232 — paramétrage comptable par entreprise (AccountingSettings).
 *
 * Couvre : GET (settings persistés ou défauts pays), PUT (upsert + validation),
 * RBAC (comptable/principal autorisés ; employé ordinaire et marketing
 * refusés), isolation tenant et provisioning des défauts à la création
 * d'entreprise (événement CompanyCreated).
 */
class AccountingSettingsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
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

    public function test_show_returns_country_defaults_when_nothing_persisted(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/settings');

        $response->assertStatus(200);
        // Défauts dérivés du pays de l'entreprise (DZ) via CountryDefaults.
        $response->assertJsonPath('data.currency', 'DZD');
        $response->assertJsonPath('data.document_language', 'fr');
        $response->assertJsonPath('data.tva_rates.0.rate', 19);
        $response->assertJsonPath('data.number_series.invoice', 'FAC');
        $response->assertJsonPath('data.template_style', 'modern');
    }

    public function test_default_tva_label_is_translated_via_label_key(): void
    {
        $manager = $this->manager($this->companyA); // DZ → défauts pays
        $manager->forceFill(['preferred_language' => 'en'])->save();
        Sanctum::actingAs($manager);

        $response = $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/accounting/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('data.tva_rates.0.rate', 19);
        $response->assertJsonPath('data.tva_rates.0.label_key', 'standard');
        $response->assertJsonPath('data.tva_rates.0.label', __('accounting.tva_label_standard', [], 'en'));
    }

    public function test_custom_tva_label_without_label_key_is_served_as_is(): void
    {
        $manager = $this->manager($this->companyA);
        $manager->forceFill(['preferred_language' => 'fr'])->save();
        Sanctum::actingAs($manager);

        $this->putJson('/api/v1/accounting/settings', [
            'tva_rates' => [['label' => 'Label personnalisé', 'rate' => 20]],
        ])->assertStatus(200);

        $manager->forceFill(['preferred_language' => 'ar'])->save();

        $response = $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/accounting/settings');

        $response->assertJsonPath('data.tva_rates.0.label', 'Label personnalisé');
        $response->assertJsonPath('data.tva_rates.0.label_key', null);
    }

    public function test_show_returns_persisted_settings(): void
    {
        app()->instance('current_company', $this->companyA);

        // Persisté sans capture de variable : le @var PHPStan pointait sur
        // $settings inexistant (varTag.variableNotFound).
        AccountingSettings::query()->create([
            'company_id' => $this->companyA->id,
            'currency' => 'EUR',
            'document_language' => 'en',
            'tva_rates' => [['label' => 'Standard', 'rate' => 20]],
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('data.currency', 'EUR');
        $response->assertJsonPath('data.document_language', 'en');
        $response->assertJsonPath('data.tva_rates.0.rate', 20);
    }

    public function test_comptable_can_update_settings(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->putJson('/api/v1/accounting/settings', [
            'currency' => 'EUR',
            'document_language' => 'en',
            'payment_terms' => '30 jours',
            'legal_mentions' => 'SARL au capital de 100 000 DZD — RC 16/00-0000000B',
            'template_style' => 'classic',
            'tva_rates' => [
                ['label' => 'Standard', 'rate' => 19],
                ['label' => 'Réduit', 'rate' => 9],
            ],
            'number_series' => [
                'invoice' => 'FAC',
                'quote' => 'DEV',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.currency', 'EUR');
        $response->assertJsonPath('data.document_language', 'en');
        $response->assertJsonPath('data.payment_terms', '30 jours');
        $response->assertJsonPath('data.legal_mentions', 'SARL au capital de 100 000 DZD — RC 16/00-0000000B');
        $response->assertJsonPath('data.template_style', 'classic');
        $response->assertJsonPath('data.tva_rates.0.label', 'Standard');
        $response->assertJsonPath('data.tva_rates.0.rate', 19);
        $response->assertJsonPath('data.number_series.invoice', 'FAC');

        $this->assertDatabaseHas('accounting_settings', [
            'company_id' => $this->companyA->id,
            'currency' => 'EUR',
        ]);
    }

    public function test_update_is_an_upsert_single_row_per_company(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->putJson('/api/v1/accounting/settings', ['currency' => 'DZD'])->assertStatus(200);
        $this->putJson('/api/v1/accounting/settings', ['currency' => 'EUR'])->assertStatus(200);

        $this->assertSame(
            1,
            DB::table('accounting_settings')->where('company_id', $this->companyA->id)->count(),
        );
        $this->assertSame(
            'EUR',
            DB::table('accounting_settings')->where('company_id', $this->companyA->id)->value('currency'),
        );
    }

    public function test_principal_can_update_settings(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $response = $this->putJson('/api/v1/accounting/settings', [
            'currency' => 'DZD',
            'document_language' => 'ar',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.document_language', 'ar');
    }

    public function test_employee_cannot_access_settings(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/accounting/settings')->assertStatus(403);
        $this->putJson('/api/v1/accounting/settings', ['currency' => 'DZD'])->assertStatus(403);
    }

    public function test_marketing_manager_role_cannot_access_settings(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->getJson('/api/v1/accounting/settings')->assertStatus(403);
    }

    public function test_validation_rejects_unsupported_currency(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->putJson('/api/v1/accounting/settings', ['currency' => 'XYZ']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('currency');
    }

    public function test_validation_rejects_unknown_document_language(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->putJson('/api/v1/accounting/settings', ['document_language' => 'de']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('document_language');
    }

    public function test_validation_rejects_invalid_tva_rate(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->putJson('/api/v1/accounting/settings', [
            'tva_rates' => [['label' => 'Standard', 'rate' => 150]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tva_rates.0.rate');
    }

    public function test_validation_rejects_unknown_number_series_key(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->putJson('/api/v1/accounting/settings', [
            'number_series' => ['facture' => 'FAC'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('number_series.facture');
    }

    public function test_settings_are_scoped_to_current_tenant(): void
    {
        // La compagnie B persiste des réglages (MAD/arabe).
        app()->instance('current_company', $this->companyB);

        AccountingSettings::query()->create([
            'company_id' => $this->companyB->id,
            'currency' => 'MAD',
            'document_language' => 'ar',
        ]);
        app()->forgetInstance('current_company');

        // La compagnie A ne doit JAMAIS voir les réglages de B : elle reçoit
        // ses propres défauts (DZ), pas les valeurs de B.
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/settings');

        $response->assertStatus(200);
        $response->assertJsonPath('data.currency', 'DZD');
        $response->assertJsonPath('data.document_language', 'fr');
    }

    public function test_company_created_event_provisions_country_defaults(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);

        event(new CompanyCreated($company));

        $this->assertDatabaseHas('accounting_settings', [
            'company_id' => $company->id,
            'currency' => 'XOF',
            'document_language' => 'fr',
        ]);

        app()->instance('current_company', $company);

        /** @var AccountingSettings $settings */
        $settings = AccountingSettings::query()
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->assertSame(18, $settings->tva_rates[0]['rate'] ?? null);
        $this->assertSame('FAC', $settings->number_series['invoice'] ?? null);
    }

    public function test_company_created_event_provisions_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        event(new CompanyCreated($company));
        event(new CompanyCreated($company));

        $this->assertSame(
            1,
            DB::table('accounting_settings')->where('company_id', $company->id)->count(),
        );
    }
}
