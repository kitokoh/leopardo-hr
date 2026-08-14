<?php

declare(strict_types=1);

namespace Tests\Feature\MultiCountry;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MULTI-PAYS (#1952) — verrous pays du tenant :
 *  (1) garde tenant.country sur import employés + QR onboarding ;
 *  (2) endpoint admin de réparation/choix du pays (refus si données de paie) ;
 *  (3) invariant 9 : changement de pays refusé après création de données de paie.
 */
class TenantCountryLocksTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeManager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);
    }

    // ── (1) Garde pays sur import employés ─────────────────────────────────

    public function test_employee_import_refused_when_tenant_country_missing(): void
    {
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create(['country' => '']);
        Sanctum::actingAs($this->makeManager($company));

        $csv = "first_name,last_name,email\nAli,Ben,ali@example.com\n";

        $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ])->assertStatus(422)->assertJsonValidationErrors('country');
    }

    public function test_employee_import_allowed_when_tenant_country_set(): void
    {
        Storage::fake('local');

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        Sanctum::actingAs($this->makeManager($company));

        $csv = "first_name,last_name,email\nAli,Ben,ali-".uniqid()."@example.com\n";

        // Le middleware passe ; le CSV est traité (pas de 422 pays). Le test
        // accepte 200/422 métier — l'important est l'absence d'erreur pays.
        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
        ]);

        $this->assertNotSame(422, $response->status());
        $this->assertNotContains('country', array_keys($response->json('errors') ?? []));
    }

    // ── (1) Garde pays sur QR onboarding ───────────────────────────────────

    public function test_qr_onboarding_create_refused_when_tenant_country_missing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => '']);
        Sanctum::actingAs($this->makeManager($company));

        $this->post('/api/v1/company/qr-onboarding/create-employee', [
            'qr_token' => 'encoded-profile',
            'email' => 'qr-'.uniqid().'@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('country');
    }

    // ── (2)+(3) Endpoint admin réparation + invariant 9 ───────────────────

    public function test_admin_can_repair_country_on_legacy_tenant_without_payroll(): void
    {
        /** @var Company $company */
        // Legacy : pays vide (timezone/currency sont NOT NULL avec défaut —
        // « legacy » = pays absent uniquement).
        $company = Company::factory()->create(['country' => '']);
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->patchJson('/api/v1/platform/companies/'.$company->id.'/country', [
            'country' => 'SN',
        ])->assertOk();

        $company->refresh();
        $this->assertSame('SN', $company->country);
        $this->assertSame('XOF', $company->currency);
        $this->assertSame('Africa/Dakar', $company->timezone);

        // #1873 — toute modification du pays est tracée dans l'audit.
        $audit = AuditLog::query()
            ->where('company_id', $company->id)
            ->where('action', 'tenant_country_changed')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit, 'Le changement de pays doit être journalisé.');
        // country est char(2) : '' stocké → '  ' (padding PostgreSQL).
        $this->assertSame('', trim((string) ($audit->old_values['country'] ?? '')));
        $this->assertSame('SN', $audit->new_values['country'] ?? null);
    }

    public function test_admin_country_repair_rejects_unknown_country(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => '']);
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->patchJson('/api/v1/platform/companies/'.$company->id.'/country', [
            'country' => 'ZZ',
        ])->assertStatus(422)->assertJsonValidationErrors('country');

        $this->assertSame('', $company->refresh()->country);
    }

    public function test_country_change_refused_after_payroll_run_invariant9(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'Africa/Algiers']);

        PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->patchJson('/api/v1/platform/companies/'.$company->id.'/country', [
            'country' => 'CI',
        ])->assertStatus(422);

        $this->assertSame('DZ', $company->refresh()->country);
    }

    public function test_country_change_refused_after_salary_structure_invariant9(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'timezone' => 'Africa/Algiers']);

        SalaryStructure::query()->create([
            'company_id' => $company->id,
            'name' => 'Grille DZ',
            'base_salary' => 60000,
            'currency' => 'DZD',
            'country_code' => 'DZ',
            'frequency' => 'monthly',
            'active' => true,
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $this->patchJson('/api/v1/platform/companies/'.$company->id.'/country', [
            'country' => 'CI',
        ])->assertStatus(422);

        $this->assertSame('DZ', $company->refresh()->country);
    }
}
