<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class SocialAccountControllerTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    private function marketingManager(Company $company): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'marketing',
        ]);
    }

    public function test_connect_creates_active_social_account(): void
    {
        Http::fake([
            'api.ayrshare.com/api/profiles/profile' => Http::response([
                'profileKey' => 'profile-key-http',
                'refId' => 'ref-1',
                'title' => 'Leopardo Marketing',
            ], 200),
        ]);

        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/marketing/social-account/connect', [
            'display_name' => 'Leopardo Marketing',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.display_name', 'Leopardo Marketing');

        $this->assertArrayNotHasKey('provider_profile_ref', $response->json('data'));
        $this->assertDatabaseHas('social_accounts', [
            'company_id' => $company->id,
            'status' => 'active',
        ]);
    }

    public function test_show_returns_404_when_no_account_connected(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/marketing/social-account')
            ->assertStatus(404)
            ->assertJsonPath('error', 'SOCIAL_ACCOUNT_NOT_FOUND');
    }

    public function test_show_returns_the_connected_account_for_the_tenant(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);

        SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'display_name' => 'Leopardo Marketing',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/marketing/social-account')
            ->assertOk()
            ->assertJsonPath('data.display_name', 'Leopardo Marketing');
    }

    public function test_disconnect_revokes_the_account(): void
    {
        $company = Company::factory()->create();
        $manager = $this->marketingManager($company);

        SocialAccount::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'ayrshare',
            'provider_profile_ref' => 'profile-key-abc',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/marketing/social-account/disconnect')
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        $this->assertDatabaseHas('social_accounts', [
            'company_id' => $company->id,
            'status' => 'revoked',
        ]);
    }

    public function test_non_marketing_manager_is_forbidden(): void
    {
        $company = Company::factory()->create();
        $rhManager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($rhManager);

        $this->getJson('/api/v1/marketing/social-account')->assertStatus(403);
    }

    public function test_employee_cannot_access_marketing_routes(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/marketing/social-account')->assertStatus(403);
    }
}
