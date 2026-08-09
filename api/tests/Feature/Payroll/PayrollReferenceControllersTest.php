<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\SalaryComponent;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Programme FOCUS — F-14 : couverture des contrôleurs de référentiel paie
 * (structures salariales, composants, barèmes IRG, cotisations sociales).
 * Ces contrôleurs CRUD étaient sans tests Feature directs, laissant le
 * coverage du module sous le seuil F-14 (≥ 80 %).
 *
 * Couvre : index/store/show/update/destroy + isolation tenant + RBAC manager.
 */
class PayrollReferenceControllersTest extends TestCase
{
    use \Tests\RefreshTenantDatabase;

    private Company $company;
    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
    }

    private function actingAsManager(): void
    {
        Sanctum::actingAs($this->manager);
    }

    // ── Salary Structures ────────────────────────────────────────────────

    public function test_salary_structure_crud_flow(): void
    {
        $this->actingAsManager();

        // index vide
        $this->getJson('/api/v1/salary-structures')->assertOk()->assertJsonCount(0, 'data');

        // store
        $created = $this->postJson('/api/v1/salary-structures', [
            'name' => 'Grille Cadre',
            'base_salary' => 120000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
        ])->assertCreated()->json('data');

        $id = $created['id'];

        // show
        $this->getJson("/api/v1/salary-structures/{$id}")->assertOk()->assertJsonPath('data.name', 'Grille Cadre');

        // index non vide
        $this->getJson('/api/v1/salary-structures')->assertOk()->assertJsonCount(1, 'data');

        // update
        $this->putJson("/api/v1/salary-structures/{$id}", ['name' => 'Grille Cadre Senior'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Grille Cadre Senior');

        // destroy
        $this->deleteJson("/api/v1/salary-structures/{$id}")->assertOk();
        $this->getJson('/api/v1/salary-structures')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_salary_structure_store_validation_errors(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/salary-structures', [
            'name' => '',
            'base_salary' => -5,
            'currency' => 'XX',
            'country_code' => 'ZZ',
        ])->assertUnprocessable();
    }

    // ── Salary Components ────────────────────────────────────────────────

    public function test_salary_component_crud_flow(): void
    {
        $this->actingAsManager();

        $structure = SalaryStructure::create([
            'company_id' => $this->company->id,
            'name' => 'Grille A',
            'base_salary' => 50000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        $created = $this->postJson('/api/v1/salary-components', [
            'salary_structure_id' => $structure->id,
            'name' => 'Salaire de base',
            'code' => 'BASE',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'amount' => 50000,
            'is_taxable' => true,
        ])->assertCreated()->json('data');

        $id = $created['id'];

        $this->getJson("/api/v1/salary-components/{$id}")->assertOk()->assertJsonPath('data.name', 'Salaire de base');
        $this->getJson('/api/v1/salary-components')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/salary-components/{$id}", ['amount' => 55000])
            ->assertOk()
            ->assertJsonPath('data.amount', 55000);

        $this->deleteJson("/api/v1/salary-components/{$id}")->assertOk();
        $this->getJson('/api/v1/salary-components')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_salary_component_requires_valid_type(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/salary-components', [
            'name' => 'Bogus',
            'code' => 'BOGUS',
            'type' => 'invalid_type',
            'calculation_type' => 'fixed',
        ])->assertUnprocessable();
    }

    // ── Tax Slabs (IRG) ──────────────────────────────────────────────────

    public function test_tax_slab_crud_flow(): void
    {
        $this->actingAsManager();

        $created = $this->postJson('/api/v1/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'Tranche 1',
            'min_amount' => 0,
            'max_amount' => 100000,
            'rate' => 0,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $id = $created['id'];

        // index scopé tenant + filtre pays
        $this->getJson('/api/v1/tax-slabs?country_code=DZ')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tax-slabs?country_code=MA')->assertOk()->assertJsonCount(0, 'data');

        $this->putJson("/api/v1/tax-slabs/{$id}", ['rate' => 5])
            ->assertOk()
            ->assertJsonPath('data.rate', 5);

        $this->deleteJson("/api/v1/tax-slabs/{$id}")->assertOk();
        $this->getJson('/api/v1/tax-slabs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_tax_slab_store_validation_errors(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/tax-slabs', [
            'country_code' => 'DZ',
            'name' => 'X',
            'min_amount' => 100,
            'max_amount' => 50, // max < min
            'rate' => 150,      // > 100
            'effective_from' => 'not-a-date',
        ])->assertUnprocessable();
    }

    // ── Social Contributions ─────────────────────────────────────────────

    public function test_social_contribution_crud_flow(): void
    {
        $this->actingAsManager();

        $created = $this->postJson('/api/v1/social-contributions', [
            'country_code' => 'DZ',
            'name' => 'CNAS',
            'code' => 'CNAS-DZ',
            'type' => 'employee',
            'rate' => 9,
            'cap' => 500000,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $id = $created['id'];

        $this->getJson('/api/v1/social-contributions?country_code=DZ')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/social-contributions?type=employer')->assertOk()->assertJsonCount(0, 'data');

        // La resource expose les champs réels (type/rate) et non plus les
        // champs fantômes employee_rate/employer_rate (corrigé #1409).
        $this->getJson('/api/v1/social-contributions')->assertOk()
            ->assertJsonPath('data.0.type', 'employee')
            ->assertJsonPath('data.0.rate', 9)
            ->assertJsonMissingPath('data.0.employee_rate')
            ->assertJsonMissingPath('data.0.employer_rate');

        $this->putJson("/api/v1/social-contributions/{$id}", ['rate' => 9.5])
            ->assertOk()
            ->assertJsonPath('data.name', 'CNAS');

        $this->deleteJson("/api/v1/social-contributions/{$id}")->assertOk();
        $this->getJson('/api/v1/social-contributions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_social_contribution_store_validation_errors(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/social-contributions', [
            'country_code' => 'DZ',
            'name' => 'X',
            'code' => 'X',
            'type' => 'nope',
            'rate' => -1,
            'effective_from' => '2026-01-01',
        ])->assertUnprocessable();
    }

    // ── RBAC : un employé non-manager n'a pas accès ──────────────────────

    public function test_employee_cannot_manage_reference_data(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/salary-structures')->assertForbidden();
        $this->getJson('/api/v1/salary-components')->assertForbidden();
        $this->getJson('/api/v1/tax-slabs')->assertForbidden();
        $this->getJson('/api/v1/social-contributions')->assertForbidden();
    }

    // ── Isolation tenant : les données d'une autre société sont invisibles ─

    public function test_reference_data_is_tenant_scoped(): void
    {
        $otherCompany = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        SalaryStructure::create([
            'company_id' => $otherCompany->id,
            'name' => 'Grille Autre',
            'base_salary' => 99999,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);
        TaxSlab::create([
            'company_id' => $otherCompany->id,
            'country_code' => 'DZ',
            'name' => 'Tranche Autre',
            'min_amount' => 0,
            'max_amount' => 999999,
            'rate' => 99,
            'fixed_deduction' => 0,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAsManager();

        $this->getJson('/api/v1/salary-structures')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/tax-slabs')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/social-contributions')->assertOk()->assertJsonCount(0, 'data');
    }

    // ── F-17 (#1595) : chiffrement au repos du metadata des documents de paie ─

    public function test_payment_document_metadata_is_encrypted_at_rest(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $doc = \App\Modules\Payroll\Domain\Models\PaymentDocument::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'document_type' => 'pay_slip',
            'status' => 'generated',
            'metadata' => [
                'net_salary' => 37500,
                'period_start' => '2026-07-01',
                'payment_reference' => 'VIR-2026-07-001',
            ],
        ]);

        // Round-trip : la lecture via le cast renvoie le tableau déchiffré.
        $fresh = $doc->fresh();
        $this->assertSame(37500, $fresh->metadata['net_salary']);
        $this->assertSame('VIR-2026-07-001', $fresh->metadata['payment_reference']);

        // Au repos (DB), la valeur ne contient ni le montant ni la référence
        // en clair — elle est chiffrée (payload non-JSON).
        $raw = \Illuminate\Support\Facades\DB::table('payment_documents')
            ->where('id', $doc->id)
            ->value('metadata');

        $this->assertIsString($raw);
        $this->assertStringNotContainsString('VIR-2026-07-001', (string) $raw);
        $this->assertStringNotContainsString('37500', (string) $raw);
        // JSON en clair commencerait par `{` ; un payload chiffré non.
        $this->assertStringStartsNotWith('{', (string) $raw);
    }

    public function test_payment_document_metadata_backfill_encrypts_plain_rows(): void
    {
        // Simule une ligne historique en clair (avant F-17) puis exécute le
        // up() de la migration de backfill : la valeur doit passer en chiffré.
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $id = \Illuminate\Support\Facades\DB::table('payment_documents')->insertGetId([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'document_type' => 'pay_slip',
            'status' => 'generated',
            'metadata' => '{"net_salary":37500,"payment_reference":"VIR-OLD-001"}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/tenant/2026_08_09_000003_encrypt_payment_documents_metadata.php');
        $migration->up();

        $raw = \Illuminate\Support\Facades\DB::table('payment_documents')
            ->where('id', $id)
            ->value('metadata');

        $this->assertStringStartsNotWith('{', (string) $raw);
        $this->assertStringNotContainsString('VIR-OLD-001', (string) $raw);

        // Idempotence : rejouer ne casse pas (la valeur est déjà chiffrée).
        $migration->up();
        $this->assertSame($raw, \Illuminate\Support\Facades\DB::table('payment_documents')->where('id', $id)->value('metadata'));
    }
}
