<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Sprint D — super-admin UI : edition d une societe (toggle features, status,
 * notes, plan). Couvre :
 *   - acces restreint super_admin_web
 *   - affichage de la page edit avec features resolues
 *   - update persiste features JSONB / status / notes / plan
 *   - rh est force a true meme si non submitte (APV L.08)
 *   - features inconnues soumises sont ignorees (whitelist KNOWN_MODULES)
 */
class PlatformCompanyEditTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function seedCompanyAndPlans(): Company
    {
        DB::table('plans')->insertOrIgnore([
            ['id' => 1, 'name' => 'Starter', 'price_monthly' => 29, 'price_yearly' => 290, 'trial_days' => 14, 'is_active' => true],
            ['id' => 2, 'name' => 'Pro', 'price_monthly' => 99, 'price_yearly' => 990, 'trial_days' => 14, 'is_active' => true],
        ]);

        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Pilote SARL',
            'slug' => 'pilote-sarl',
            'sector' => 'Industrie',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@pilote-sarl.dz',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'language' => 'fr',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
            'features' => ['rh' => true],
        ]);
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);
    }

    public function test_edit_page_requires_super_admin_authentication(): void
    {
        $company = $this->seedCompanyAndPlans();

        $response = $this->get(route('platform.companies.edit', ['company' => $company->id]));

        // Guard auth:super_admin_web declenche un redirect vers la route de login
        // par defaut (Laravel route 'login') ; on verifie juste qu on est
        // redirige (pas 200) et qu aucune donnee de la societe ne fuite.
        $response->assertStatus(302);
        $response->assertDontSee('Pilote SARL');
    }

    public function test_super_admin_can_view_edit_page(): void
    {
        $company = $this->seedCompanyAndPlans();
        $superAdmin = $this->superAdmin();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_web')
            ->get(route('platform.companies.edit', ['company' => $company->id]));

        $response->assertOk();
        $response->assertSee('Pilote SARL');
        $response->assertSee('Modules actifs');
        $response->assertSee('data-testid="feature-finance"', false);
        $response->assertSee('data-testid="feature-cameras"', false);
        $response->assertSee('data-testid="feature-muhasebe"', false);
        $response->assertSee('data-testid="feature-leo_ai"', false);
    }

    public function test_update_toggles_features_and_persists_status_notes_plan(): void
    {
        $company = $this->seedCompanyAndPlans();
        $superAdmin = $this->superAdmin();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_web')
            ->put(route('platform.companies.update', ['company' => $company->id]), [
                'status' => 'suspended',
                'plan_id' => 2,
                'notes' => 'Client a suspendu, relance dans 15j.',
                'features' => [
                    'finance' => '1',
                    'cameras' => '1',
                ],
            ]);

        $response->assertRedirect(route('platform.companies.edit', ['company' => $company->id]));

        $company->refresh();
        $this->assertSame('suspended', $company->status);
        $this->assertSame(2, $company->plan_id);
        $this->assertSame('Client a suspendu, relance dans 15j.', $company->notes);
        $this->assertTrue($company->hasFeature('rh'));
        $this->assertTrue($company->hasFeature('finance'));
        $this->assertTrue($company->hasFeature('cameras'));
        $this->assertFalse($company->hasFeature('muhasebe'));
        $this->assertFalse($company->hasFeature('leo_ai'));
    }

    public function test_update_forces_rh_always_true_and_ignores_unknown_modules(): void
    {
        $company = $this->seedCompanyAndPlans();
        $company->features = ['rh' => true, 'finance' => true];
        $company->save();

        $superAdmin = $this->superAdmin();

        $this
            ->actingAs($superAdmin, 'super_admin_web')
            ->put(route('platform.companies.update', ['company' => $company->id]), [
                'status' => 'active',
                'plan_id' => 1,
                'notes' => null,
                'features' => [
                    // rh volontairement omis
                    // finance volontairement omis (desactivation)
                    'hacker_backdoor' => '1',
                ],
            ])
            ->assertRedirect();

        $company->refresh();
        $this->assertTrue($company->hasFeature('rh'), 'RH must stay active (APV L.08 — RH base).');
        $this->assertFalse($company->hasFeature('finance'), 'Finance should be disabled when unchecked.');
        $this->assertArrayNotHasKey('hacker_backdoor', $company->features ?? []);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $company = $this->seedCompanyAndPlans();
        $superAdmin = $this->superAdmin();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_web')
            ->from(route('platform.companies.edit', ['company' => $company->id]))
            ->put(route('platform.companies.update', ['company' => $company->id]), [
                'status' => 'elevated',
                'plan_id' => 1,
            ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_index_shows_active_modules_and_edit_link(): void
    {
        $company = $this->seedCompanyAndPlans();
        $company->features = ['rh' => true, 'cameras' => true];
        $company->save();

        $superAdmin = $this->superAdmin();

        $response = $this
            ->actingAs($superAdmin, 'super_admin_web')
            ->get(route('platform.companies.index'));

        $response->assertOk();
        $response->assertSee('Pilote SARL');
        $response->assertSee('Cameras');
        $response->assertSee(route('platform.companies.edit', ['company' => $company->id]), false);
    }
}
