<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Accounting\Domain\Models\BankStatement;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Rapprochement bancaire Phase D (#5435) — US1 : import CSV de relevé.
 */
class BankStatementImportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function manager(Company $company, string $managerRole = 'comptable'): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $manager;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        return $employee;
    }

    private function csvFile(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('releve.csv', $content);
    }

    private function validCsv(): string
    {
        return "date;label;amount;reference\n"
            ."2026-08-05;Paiement facture FAC-2026-0001;1190.00;VIR-2026-001\n"
            ."2026-08-06;Paiement facture FAC-2026-0002;500.00;CHEQUE-2026-002\n";
    }

    public function test_import_valid_csv_creates_statement_and_lines(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/accounting/bank-statements/import', [
            'statement_period' => '2026-08',
            'import_reference' => 'RELEVE-AOÛT-2026',
            'file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.imported', 2);
        $response->assertJsonPath('data.skipped', 0);
        $response->assertJsonPath('data.errors', []);
        $response->assertJsonPath('data.statement.statement_period', '2026-08');
        $response->assertJsonPath('data.statement.status', 'imported');

        $this->assertSame(1, BankStatement::query()->count());
        $this->assertSame(2, BankStatement::query()->firstOrFail()->lines()->count());
    }

    public function test_import_invalid_header_returns_422_and_inserts_nothing(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $csv = "foo;bar\n1;2\n";
        $response = $this->postJson('/api/v1/accounting/bank-statements/import', [
            'statement_period' => '2026-08',
            'import_reference' => 'RELEVE-INVALIDE',
            'file' => $this->csvFile($csv),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, BankStatement::query()->count());
    }

    public function test_duplicate_import_returns_409_and_inserts_nothing(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $payload = [
            'statement_period' => '2026-08',
            'import_reference' => 'RELEVE-DOUBLON',
            'file' => $this->csvFile($this->validCsv()),
        ];

        $this->postJson('/api/v1/accounting/bank-statements/import', $payload)->assertCreated();

        $response = $this->postJson('/api/v1/accounting/bank-statements/import', $payload);
        $response->assertStatus(409);
        $response->assertJsonPath('error', 'BANK_STATEMENT_DUPLICATE_IMPORT');

        $this->assertSame(1, BankStatement::query()->count());
    }

    public function test_partial_invalid_lines_are_reported_with_line_numbers(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        $csv = "date;label;amount;reference\n"
            ."2026-08-05;Paiement OK;1190.00;VIR-2026-001\n"
            ."2026-13-99;Date invalide;500.00;\n"
            ."2026-08-06;;abc;\n";

        $response = $this->postJson('/api/v1/accounting/bank-statements/import', [
            'statement_period' => '2026-08',
            'import_reference' => 'RELEVE-PARTIEL',
            'file' => $this->csvFile($csv),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.imported', 1);
        $response->assertJsonPath('data.skipped', 2);
        $errors = $response->json('data.errors');
        $this->assertCount(2, $errors);
        $this->assertSame(3, $errors[0]['line']);
        $this->assertSame(4, $errors[1]['line']);
    }

    public function test_import_requires_manager_role(): void
    {
        $company = $this->company();
        Sanctum::actingAs($this->employee($company));

        $response = $this->postJson('/api/v1/accounting/bank-statements/import', [
            'statement_period' => '2026-08',
            'import_reference' => 'RELEVE-RBAC',
            'file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertForbidden();
        $this->assertSame(0, BankStatement::query()->count());
    }

    public function test_cross_tenant_statement_is_404(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();

        app()->instance('current_company', $companyA);
        $statementA = BankStatement::create([
            'company_id' => $companyA->id,
            'statement_period' => '2026-08',
            'import_reference' => 'RELEVE-A',
            'status' => 'imported',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($this->manager($companyB));

        $response = $this->getJson('/api/v1/accounting/bank-statements/'.$statementA->id);
        $response->assertNotFound();
    }
}
