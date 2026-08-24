<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Enums\DocumentStatus;
use App\Modules\Accounting\Domain\Enums\DocumentType;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5271 — déclaration TVA simplifiée par période.
 *
 * Couvre : agrégation golden (collectée/déductible/net par taux), exclusions
 * (brouillon, annulé, hors période, types non concernés), export CSV, RBAC
 * (comptable/principal ; employé et marketing refusés), isolation tenant et
 * validation de la période.
 */
class VatDeclarationTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function document(Company $company, array $overrides = []): AccountingDocument
    {
        /** @var AccountingContact $contact */
        $contact = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client déclaration',
        ]);

        /** @var AccountingDocument $document */
        $document = AccountingDocument::query()->create(array_merge([
            'company_id' => $company->id,
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-'.strtoupper(uniqid()),
            'status' => DocumentStatus::Sent->value,
            'contact_id' => $contact->id,
            'issue_date' => '2026-08-15',
            'currency' => 'DZD',
            'subtotal_ht' => 0.0,
            'tax_amount' => 0.0,
            'total_ttc' => 0.0,
            'tva_rate' => 19.0,
        ], $overrides));

        return $document;
    }

    /**
     * Scénario golden août 2026 (calculé à la main) :
     *  - collectée : facture 19 % (1000/190/1190) + facture 9 % (5000/450/5450)
     *    + reçu 19 % (200/38/238) ;
     *  - déductible : avoir 19 % (300/57/357) ;
     *  - exclus : proforma, brouillon, annulé, document hors période.
     */
    private function seedGoldenAugust(Company $company): void
    {
        app()->instance('current_company', $company);

        $this->document($company, [
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-2026-0001',
            'issue_date' => '2026-08-05',
            'subtotal_ht' => 1000.0,
            'tax_amount' => 190.0,
            'total_ttc' => 1190.0,
            'tva_rate' => 19.0,
        ]);
        $this->document($company, [
            'type' => DocumentType::Invoice->value,
            'number' => 'FAC-2026-0002',
            'issue_date' => '2026-08-12',
            'subtotal_ht' => 5000.0,
            'tax_amount' => 450.0,
            'total_ttc' => 5450.0,
            'tva_rate' => 9.0,
        ]);
        $this->document($company, [
            'type' => DocumentType::Receipt->value,
            'number' => 'REC-2026-0001',
            'issue_date' => '2026-08-20',
            'subtotal_ht' => 200.0,
            'tax_amount' => 38.0,
            'total_ttc' => 238.0,
            'tva_rate' => 19.0,
        ]);
        $this->document($company, [
            'type' => DocumentType::CreditNote->value,
            'number' => 'AVOIR-2026-0001',
            'issue_date' => '2026-08-25',
            'subtotal_ht' => 300.0,
            'tax_amount' => 57.0,
            'total_ttc' => 357.0,
            'tva_rate' => 19.0,
        ]);

        // Exclus de la déclaration.
        $this->document($company, [
            'type' => DocumentType::Proforma->value,
            'number' => 'PRO-2026-0001',
            'subtotal_ht' => 1000.0,
            'tax_amount' => 190.0,
            'total_ttc' => 1190.0,
        ]);
        $this->document($company, [
            'number' => 'FAC-2026-0003',
            'status' => DocumentStatus::Draft->value,
            'subtotal_ht' => 999.0,
            'tax_amount' => 189.81,
            'total_ttc' => 1188.81,
        ]);
        $this->document($company, [
            'number' => 'FAC-2026-0004',
            'status' => DocumentStatus::Cancelled->value,
            'subtotal_ht' => 999.0,
            'tax_amount' => 189.81,
            'total_ttc' => 1188.81,
        ]);
        $this->document($company, [
            'number' => 'FAC-2026-0005',
            'issue_date' => '2026-07-31',
            'subtotal_ht' => 500.0,
            'tax_amount' => 95.0,
            'total_ttc' => 595.0,
        ]);

        app()->forgetInstance('current_company');
    }

    public function test_vat_declaration_golden_august(): void
    {
        $this->seedGoldenAugust($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-08');

        $response->assertStatus(200);
        $response->assertJsonPath('data.period.label', '2026-08');
        $response->assertJsonPath('data.period.from', '2026-08-01');
        $response->assertJsonPath('data.period.to', '2026-08-31');
        $response->assertJsonPath('data.currency', 'DZD');

        // Collectée : 6200 / 678 / 6878.
        $response->assertJsonPath('data.collected.base', 6200);
        $response->assertJsonPath('data.collected.tax', 678);
        $response->assertJsonPath('data.collected.total', 6878);
        $response->assertJsonCount(2, 'data.collected.by_rate');
        $response->assertJsonPath('data.collected.by_rate.0.rate', 9);
        $response->assertJsonPath('data.collected.by_rate.0.base', 5000);
        $response->assertJsonPath('data.collected.by_rate.0.tax', 450);
        $response->assertJsonPath('data.collected.by_rate.1.rate', 19);
        $response->assertJsonPath('data.collected.by_rate.1.base', 1200);
        $response->assertJsonPath('data.collected.by_rate.1.tax', 228);

        // Déductible : 300 / 57 / 357.
        $response->assertJsonPath('data.deductible.base', 300);
        $response->assertJsonPath('data.deductible.tax', 57);
        $response->assertJsonPath('data.deductible.total', 357);

        // Net : 5900 / 621 / 6521.
        $response->assertJsonPath('data.net.base', 5900);
        $response->assertJsonPath('data.net.tax', 621);
        $response->assertJsonPath('data.net.total', 6521);
    }

    public function test_vat_declaration_empty_period_returns_zeros(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-09');

        $response->assertStatus(200);
        $response->assertJsonPath('data.collected.base', 0);
        $response->assertJsonPath('data.collected.tax', 0);
        $response->assertJsonPath('data.collected.by_rate', []);
        $response->assertJsonPath('data.deductible.base', 0);
        $response->assertJsonPath('data.net.tax', 0);
    }

    public function test_vat_declaration_csv_export(): void
    {
        $this->seedGoldenAugust($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->get('/api/v1/accounting/reports/vat-declaration?period=2026-08&format=csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="vat-declaration-2026-08.csv"');

        $csv = $response->getContent();
        if ($csv === false) {
            $this->fail('La réponse CSV de la déclaration TVA est vide.');
        }

        $this->assertStringContainsString('periode,2026-08,devise,DZD', $csv);
        $this->assertStringContainsString('type,taux,assiette_ht,taxe,total_ttc', $csv);
        $this->assertStringContainsString('collectee,9,5000,450,5450', $csv);
        $this->assertStringContainsString('collectee,19,1200,228,1428', $csv);
        $this->assertStringContainsString('deductible,19,300,57,357', $csv);
        $this->assertStringContainsString('net,,5900,621,6521', $csv);
    }

    public function test_vat_declaration_is_tenant_scoped(): void
    {
        $this->seedGoldenAugust($this->companyA);
        // La compagnie B a aussi des documents : ils ne doivent JAMAIS
        // apparaître dans la déclaration de A.
        $this->seedGoldenAugust($this->companyB);

        Sanctum::actingAs($this->manager($this->companyA));

        $response = $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-08');

        $response->assertStatus(200);
        $response->assertJsonPath('data.collected.base', 6200);
        $response->assertJsonPath('data.collected.tax', 678);
    }

    public function test_principal_can_access_vat_declaration(): void
    {
        $this->seedGoldenAugust($this->companyA);

        Sanctum::actingAs($this->manager($this->companyA, 'principal'));

        $response = $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-08');

        $response->assertStatus(200);
        $response->assertJsonPath('data.currency', 'DZD');
    }

    public function test_employee_cannot_access_vat_declaration(): void
    {
        Sanctum::actingAs($this->ordinaryEmployee($this->companyA));

        $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-08')->assertStatus(403);
    }

    public function test_marketing_manager_role_cannot_access_vat_declaration(): void
    {
        Sanctum::actingAs($this->manager($this->companyA, 'marketing'));

        $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-08')->assertStatus(403);
    }

    public function test_vat_declaration_rejects_invalid_period(): void
    {
        Sanctum::actingAs($this->manager($this->companyA));

        $this->getJson('/api/v1/accounting/reports/vat-declaration?period=2026-13')->assertStatus(422);
        $this->getJson('/api/v1/accounting/reports/vat-declaration?period=août-2026')->assertStatus(422);
        $this->getJson('/api/v1/accounting/reports/vat-declaration?format=xml')->assertStatus(422);
    }
}
