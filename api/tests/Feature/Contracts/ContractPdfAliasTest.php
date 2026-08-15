<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Contracts\ContractDocumentGeneratorInterface;
use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QA wave 2026-08-14 — T001 (#2226).
 *
 * La Web App appelle GET /contracts/{id}/pdf (404 avant cet alias).
 * L'alias doit servir exactement le même flux que generate-pdf
 * (mêmes contrôles tenant + rôle) et renvoyer un PDF.
 */
class ContractPdfAliasTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContractSchemaIfNeeded();
        $this->app->bind(ContractDocumentGeneratorInterface::class, function (): ContractDocumentGeneratorInterface {
            return new class implements ContractDocumentGeneratorInterface
            {
                public function generate(Contract $contract): string
                {
                    return '%PDF-1.4 fake-contract-content';
                }
            };
        });
    }

    private function createContractSchemaIfNeeded(): void
    {
        if (Schema::hasTable('contracts')) {
            return;
        }

        Schema::create('contracts', function ($t) {
            $t->increments('id');
            $t->uuid('company_id');
            $t->unsignedInteger('employee_id');
            $t->string('contract_type', 20);
            $t->string('reference', 50)->nullable();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('job_title', 150)->nullable();
            $t->unsignedInteger('department_id')->nullable();
            $t->unsignedInteger('position_id')->nullable();
            $t->decimal('base_salary', 12, 2)->default(0);
            $t->string('currency', 3)->default('DZD');
            $t->string('salary_frequency', 10)->default('monthly');
            $t->decimal('work_hours_per_week', 5, 2)->nullable();
            $t->date('probation_end_date')->nullable();
            $t->json('benefits')->nullable();
            $t->json('clauses')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('signed_at')->nullable();
            $t->string('signed_document_path')->nullable();
            $t->text('termination_reason')->nullable();
            $t->timestamp('terminated_at')->nullable();
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();
        });
    }

    private function makeManagerAndCompany(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Pdf Alias Co',
            'slug' => 'pdf-alias-co',
            'sector' => 'construction',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'pdf-alias@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        /** @var Employee $manager */
        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'MGR-PDFA',
            'first_name' => 'Boss',
            'last_name' => 'RH',
            'email' => 'boss@pdf-alias.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        /** @var Employee $employee */
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-PDFA',
            'first_name' => 'Yacine',
            'last_name' => 'B',
            'email' => 'emp@pdf-alias.test',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        /** @var Contract $contract */
        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'reference' => 'PDFA-001',
            'start_date' => '2026-06-01',
            'base_salary' => 85000,
            'status' => 'active',
        ]);

        return [$company, $manager, $contract];
    }

    public function test_pdf_alias_returns_pdf_for_same_tenant_manager(): void
    {
        [, $manager, $contract] = $this->makeManagerAndCompany();
        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/contracts/{$contract->id}/pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent() ?: $response->content());
    }

    public function test_pdf_alias_404_for_cross_tenant_manager(): void
    {
        [$company, $manager, $contract] = $this->makeManagerAndCompany();

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create([
            'name' => 'Other Pdf Co',
            'slug' => 'other-pdf-co',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'other-pdf@test.com',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        /** @var Employee $otherManager */
        $otherManager = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'matricule' => 'MGR-PDFB',
            'first_name' => 'Other',
            'last_name' => 'Manager',
            'email' => 'other-manager@pdf-alias.test',
            'password_hash' => Hash::make('password'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        Sanctum::actingAs($otherManager);

        $response = $this->getJson("/api/v1/contracts/{$contract->id}/pdf");

        $response->assertNotFound();
    }
}
