<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3065 — POST /employees/link-user : employee_id doit appartenir à
 * l'entreprise de l'acteur (jamais de lien vers un employé d'une autre
 * société, même via un id deviné).
 */
class UserEmployeeLinkCrossTenantTest extends TestCase
{
    use RefreshTenantDatabase;

    private bool $createdLinkTable = false;

    protected function setUp(): void
    {
        parent::setUp();

        // tables publiques requises par le flux link (créées par la migration
        // 2026_05_02_100001 dans les environnements migrés ; défensif ici).
        if (! Schema::hasTable('user_employee_links')) {
            $this->createdLinkTable = true;
            Schema::create('user_employee_links', function ($table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('company_id');
                $table->string('status')->default('active');
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdLinkTable) {
            Schema::dropIfExists('user_employee_links');
        }
        parent::tearDown();
    }

    public function test_manager_cannot_link_employee_of_another_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['schema_name' => 'shared_tenants']);
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $ownEmployee */
        $ownEmployee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['schema_name' => 'shared_tenants']);
        /** @var Employee $foreignEmployee */
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        /** @var User $user */
        $user = User::create([
            'first_name' => 'Jean',
            'last_name' => 'Test',
            'email' => 'jean.link.'.uniqid().'@example.com',
            'password_hash' => Hash::make('secret123'),
        ]);

        Sanctum::actingAs($manager);

        // Employé d'une autre entreprise → 404 (anti-énumération).
        $this->postJson('/api/v1/employees/link-user', [
            'email' => $user->email,
            'employee_id' => $foreignEmployee->id,
        ])->assertNotFound();

        // Employé de sa propre entreprise → 201.
        $this->postJson('/api/v1/employees/link-user', [
            'email' => $user->email,
            'employee_id' => $ownEmployee->id,
        ])->assertCreated();

        // Un employé (non manager) ne peut pas lier.
        /** @var Employee $plainEmployee */
        $plainEmployee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($plainEmployee);
        $this->postJson('/api/v1/employees/link-user', [
            'email' => $user->email,
            'employee_id' => $ownEmployee->id,
        ])->assertForbidden();
    }
}
