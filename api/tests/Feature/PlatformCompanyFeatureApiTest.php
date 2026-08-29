<?php

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
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
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

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

        // BC-01 (deep-maturity) : tout changement de feature flags (activation /
        // désactivation de module = changement d'accès) est audité. La table
        // vit dans le schéma tenant partagé → qualification explicite.
        $this->assertDatabaseHas('shared_tenants.audit_logs', [
            'action' => 'platform.company.features.update',
            'module' => 'platform',
            'company_id' => $company->id,
        ]);

        $audit = DB::table('shared_tenants.audit_logs')
            ->where('action', 'platform.company.features.update')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($audit);

        $newValues = json_decode((string) $audit->new_values, true);
        $oldValues = json_decode((string) $audit->old_values, true);

        // new_values : les modules activés par la requête sont à true.
        $this->assertTrue($newValues['rh'] ?? false);
        $this->assertTrue($newValues['finance'] ?? false);
        $this->assertTrue($newValues['cameras'] ?? false);
        // old_values : état précédent (finance/cameras absents ou faux).
        $this->assertTrue($oldValues['rh'] ?? false);
        $this->assertFalse($oldValues['finance'] ?? false);
        $this->assertFalse($oldValues['cameras'] ?? false);
        // Les deux états diffèrent : le changement est tracé.
        $this->assertNotSame($oldValues, $newValues);
    }
}
