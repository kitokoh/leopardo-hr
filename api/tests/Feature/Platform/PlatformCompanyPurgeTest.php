<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7475 — suppression sûre d'un tenant : parcours en deux temps.
 *
 * Les cinq critères d'acceptation de l'issue sont couverts, un par test :
 *   1. aucune suppression sans désactivation préalable ;
 *   2. confirmation par ressaisie du nom exact ;
 *   3. inventaire chiffré avant validation ;
 *   4. opération auditée et résultat consultable ;
 *   5. société de test supprimable de bout en bout ; société avec données de
 *      paie non supprimable sans choix explicite (purge ou anonymisation).
 */
class PlatformCompanyPurgeTest extends TestCase
{
    use RefreshTenantDatabase;

    private function platformAdmin(): SuperAdmin
    {
        $admin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'purge-admin@leopardo-rh.com',
        ]);
        $admin->forceFill(['password_hash' => Hash::make('admin')])->save();

        return $admin;
    }

    private function preview(SuperAdmin $admin, Company $company): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin, 'super_admin_api')
            ->getJson('/api/v1/platform/companies/'.$company->id.'/purge-preview');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function purge(SuperAdmin $admin, Company $company, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin, 'super_admin_api')
            ->postJson('/api/v1/platform/companies/'.$company->id.'/purge', $payload);
    }

    private function companyWithEmployees(int $count = 2): Company
    {
        /** @var Company $company */
        $company = Company::factory()->suspended()->create();

        Employee::factory()->count($count)->create(['company_id' => $company->id]);

        return $company;
    }

    // ---------------------------------------------------------------- critère 3

    public function test_preview_refuses_an_active_company_and_explains_why(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        $response = $this->preview($this->platformAdmin(), $company)->assertOk();

        $response->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.blocked_reason', 'COMPANY_NOT_SUSPENDED')
            ->assertJsonPath('data.required_confirmation', $company->name);
    }

    public function test_preview_returns_a_quantified_inventory(): void
    {
        $company = $this->companyWithEmployees(3);

        $response = $this->preview($this->platformAdmin(), $company)->assertOk();

        $response->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.inventory.schema', 'shared_tenants');

        $employees = $response->json('data.inventory.resources.employees');
        $this->assertIsInt($employees);
        $this->assertGreaterThanOrEqual(3, $employees);
        $this->assertGreaterThanOrEqual(3, $response->json('data.inventory.total_rows'));
    }

    // ---------------------------------------------------------------- critère 1

    public function test_purge_is_refused_without_prior_suspension(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        Employee::factory()->create(['company_id' => $company->id]);

        $this->purge($this->platformAdmin(), $company, ['confirm_name' => $company->name])
            ->assertStatus(409);

        // Rien n'a été touché : ni la société, ni ses employés.
        $this->assertTrue(DB::table('public.companies')->where('id', $company->id)->exists());
        $this->assertSame(
            1,
            (int) DB::table('shared_tenants.employees')->where('company_id', $company->id)->count()
        );
    }

    // ---------------------------------------------------------------- critère 2

    public function test_purge_requires_the_exact_company_name(): void
    {
        $company = $this->companyWithEmployees(1);

        $this->purge($this->platformAdmin(), $company, ['confirm_name' => 'Pas le bon nom'])
            ->assertStatus(422);

        $this->assertTrue(DB::table('public.companies')->where('id', $company->id)->exists());
    }

    // ---------------------------------------------------- critères 4 et 5 (purge)

    public function test_test_company_is_purged_end_to_end_and_the_result_is_consultable(): void
    {
        $company = $this->companyWithEmployees(2);
        $companyName = (string) $company->name;
        $companyId = (string) $company->id;
        $admin = $this->platformAdmin();

        $response = $this->purge($admin, $company, [
            'confirm_name' => $companyName,
            'mode' => 'purge',
            'reason' => 'Espace de test créé par erreur',
        ])->assertStatus(201);

        $response->assertJsonPath('data.mode', 'purge')
            ->assertJsonPath('data.status', 'completed');

        // Purge réelle : plus de société, plus d'employés du tenant.
        $this->assertFalse(DB::table('public.companies')->where('id', $companyId)->exists());
        $this->assertSame(
            0,
            (int) DB::table('shared_tenants.employees')->where('company_id', $companyId)->count()
        );

        // Critère 4 : la trace survit à la purge et reste consultable.
        $journal = $this->actingAs($admin, 'super_admin_api')
            ->getJson('/api/v1/platform/company-purges')
            ->assertOk();

        $entries = collect($journal->json('data'));
        $entry = $entries->firstWhere('company_id', $companyId);

        $this->assertNotNull($entry, 'La purge doit laisser une entrée consultable.');
        $this->assertSame('purge', $entry['mode']);
        $this->assertSame('completed', $entry['status']);
        $this->assertSame($companyName, $entry['company_name']);
        $this->assertSame('Espace de test créé par erreur', $entry['reason']);
        $this->assertNotEmpty($entry['volumes'], 'Les volumes détruits doivent être tracés.');
    }

    // -------------------------------------------------- critères 5 (payroll)

    public function test_company_with_payroll_data_requires_an_explicit_mode_then_anonymisation(): void
    {
        $company = $this->companyWithEmployees(1);
        $companyId = (string) $company->id;
        $admin = $this->platformAdmin();

        DB::table('shared_tenants.payroll_runs')->insert([
            'company_id' => $companyId,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'validated',
            'total_net' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sans mode : refus explicite (obligation de conservation de la paie).
        $this->purge($admin, $company, ['confirm_name' => $company->name])
            ->assertStatus(422);

        // Le refus est motivé et l'inventaire annonce la paie.
        $preview = $this->preview($admin, $company)->assertOk();
        $preview->assertJsonPath('data.inventory.requires_explicit_mode', true)
            ->assertJsonPath('data.mode_required', true);

        // Avec `anonymise` : les données identifiantes partent, la paie reste.
        $response = $this->purge($admin, $company, [
            'confirm_name' => $company->name,
            'mode' => 'anonymise',
        ])->assertStatus(201);

        $response->assertJsonPath('data.mode', 'anonymise');

        $this->assertSame(
            1,
            (int) DB::table('shared_tenants.payroll_runs')->where('company_id', $companyId)->count(),
            'La paie est conservée en mode anonymisation.'
        );

        $employees = DB::table('shared_tenants.employees')->where('company_id', $companyId)->get();
        $this->assertCount(1, $employees);
        $this->assertSame('Anonymisé', $employees->first()->first_name);
        $this->assertSame('Anonymisé', $employees->first()->last_name);
        $this->assertStringContainsString('@purged.invalid', (string) $employees->first()->email);

        $companyRow = DB::table('public.companies')->where('id', $companyId)->first();
        $this->assertNotNull($companyRow, 'La société est conservée en mode anonymisation.');
        $this->assertStringContainsString('anonymisée', (string) $companyRow->name);
        $this->assertStringContainsString('@purged.invalid', (string) $companyRow->email);
    }
}
