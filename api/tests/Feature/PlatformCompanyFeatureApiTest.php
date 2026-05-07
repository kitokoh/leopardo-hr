<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformCompanyFeatureApiTest extends TestCase
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

    public function test_super_admin_can_view_and_update_company_feature_flags(): void
    {
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson("/api/v1/platform/companies/{$company->id}/features")
            ->assertOk()
            ->assertJsonPath('data.features.rh', true)
            ->assertJsonPath('data.features.finance', false);

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => [
                'rh' => false,
                'finance' => true,
                'cameras' => true,
                'unknown' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.rh', true)
            ->assertJsonPath('data.features.finance', true)
            ->assertJsonPath('data.features.cameras', true);

        $company->refresh();
        $this->assertTrue($company->hasFeature('rh'));
        $this->assertTrue($company->hasFeature('finance'));
        $this->assertArrayNotHasKey('unknown', $company->features ?? []);
    }
}
