<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Facades\Lang;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\ProductionConfidenceRules;
use Tests\TestCase;

/**
 * Issue #1814 — Simulation d'impact d'un barème fiscal (dry-run, non persistant).
 */
class PayrollSimulationControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected SuperAdmin $superAdmin;

    protected Employee $manager;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-simulate@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $this->employee = $employee;
    }

    public function test_simulate_returns_correct_breakdown(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
            'slabs_override' => [
                ['min' => 0, 'max' => 100000, 'rate' => 23, 'fixed_deduction' => 0],
            ],
        ])->assertOk();

        $data = $response->json('data');

        // DZ : CNAS 9 % employé → assiette 54 600 → impôt 23 % (barème mono-tranche).
        $this->assertSame(60000.0, (float) $data['gross']);
        $this->assertSame(5400.0, (float) $data['social_employee']);
        $this->assertSame(15600.0, (float) $data['social_employer']);
        $this->assertSame(54600.0, (float) $data['tax_base']);
        $this->assertNotEmpty($data['income_tax_by_slab']);
        $this->assertSame(23.0, (float) $data['income_tax_by_slab'][0]['rate']);
        $this->assertSame(54600.0, (float) $data['income_tax_by_slab'][0]['taxable_amount']);
        // Impôt final = 12 558 (abattement DZ appliqué par le moteur).
        $this->assertGreaterThan(0.0, (float) $data['income_tax']);
        $this->assertSame(round(60000 - 5400 - (float) $data['income_tax'], 2), (float) $data['net']);
        $this->assertSame(75600.0, (float) $data['total_cost']);
    }

    public function test_simulate_does_not_persist(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'CM',
            'gross_salary' => 400000,
            'slabs_override' => [
                ['min' => 0, 'max' => 500000, 'rate' => 10, 'fixed_deduction' => 0],
            ],
        ])->assertOk();

        // Aucune ligne créée/modifiée en base.
        $this->assertSame(0, TaxSlab::query()->count());
    }

    public function test_platform_admin_can_simulate_via_admin_route(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/payroll/simulate', [
            'country_code' => 'DZ',
            'gross_salary' => 100000,
        ])->assertOk()
            ->assertJsonPath('data.country_code', 'DZ');
    }

    public function test_unauthorized_user_cannot_simulate(): void
    {
        // Employé simple → 403 sur la route tenant.
        Sanctum::actingAs($this->employee);
        $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
        ])->assertStatus(403);

        // Non authentifié → 401 sur la route admin.
        $this->postJson('/api/v1/admin/payroll/simulate', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
        ])->assertStatus(401);
    }

    /**
     * Issue #1872 — la simulation expose le niveau de confiance ET
     * l'avertissement de conformité localisé (catalogue payroll.confidence.*) :
     * une simulation sur une règle pilote ne doit jamais passer pour une
     * paie légalement certifiée.
     */
    public function test_simulate_exposes_confidence_level_and_localized_compliance_warning(): void
    {
        Sanctum::actingAs($this->manager);

        $data = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'DZ',
            'gross_salary' => 60000,
        ])->assertOk()->json('data');

        $this->assertSame('pilot', $data['confidence_level']);
        $this->assertSame(
            Lang::get('payroll.confidence.pilot.message', ['country' => 'DZ']),
            $data['compliance_warning'],
        );
    }

    public function test_simulate_exposes_placeholder_confidence_for_placeholder_rules(): void
    {
        Sanctum::actingAs($this->manager);

        $data = $this->postJson('/api/v1/payroll/simulate', [
            'country_code' => 'CA',
            'gross_salary' => 5000,
        ])->assertOk()->json('data');

        $this->assertSame('placeholder', $data['confidence_level']);
        $this->assertSame(
            Lang::get('payroll.confidence.placeholder.message', ['country' => 'CA']),
            $data['compliance_warning'],
        );
    }

    public function test_simulate_exposes_production_confidence_for_production_rules(): void
    {
        // Aucune juridiction réelle n'est 'production' : stub dédié (#1872),
        // injecté via le conteneur (mêmes règles que le registre par défaut).
        $this->app->instance(PayrollCalculator::class, new PayrollCalculator([new ProductionConfidenceRules]));

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $data = $this->postJson('/api/v1/admin/payroll/simulate', [
            'country_code' => 'ZZ',
            'gross_salary' => 100000,
        ])->assertOk()->json('data');

        $this->assertSame('production', $data['confidence_level']);
        $this->assertSame(
            Lang::get('payroll.confidence.production.message', ['country' => 'ZZ']),
            $data['compliance_warning'],
        );
    }
}
