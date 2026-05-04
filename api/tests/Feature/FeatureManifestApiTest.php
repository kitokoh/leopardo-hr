<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Feature;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests d'intégration pour l'API du manifeste des fonctionnalités
 */
class FeatureManifestApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $user;

    private Employee $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur normal
        $this->user = Employee::factory()->create([
            'role' => 'employee',
        ]);

        // Créer un utilisateur admin (manager principal)
        $this->adminUser = Employee::factory()->create([
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_access_manifest(): void
    {
        // Act
        $response = $this->getJson('/api/v1/features/manifest');

        // Assert
        $response->assertStatus(401);
    }

    /** @test */
    public function it_can_get_feature_manifest(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        Feature::factory()->count(3)->create([
            'status' => 'active',
            'mobile_version_min' => '1.0.0',
        ]);

        // Act
        $response = $this->getJson('/api/v1/features/manifest?mobile_version=1.0.0');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'version',
                    'generated_at',
                    'mobile_version_min',
                    'mobile_version_target',
                    'total_features',
                    'features' => [
                        '*' => [
                            'key',
                            'title',
                            'description',
                            'endpoint',
                            'methods',
                            'permissions',
                        ],
                    ],
                    'user_id',
                    'user_role',
                ],
                'meta' => [
                    'generated_for_user',
                    'mobile_version',
                    'api_version',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($this->user->id, $response->json('data.user_id'));
    }

    /** @test */
    public function it_can_get_compatible_features(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        // Fonctionnalité compatible avec version 1.0.0
        Feature::factory()->create([
            'key' => 'compatible_feature',
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => null,
            'status' => 'active',
        ]);

        // Fonctionnalité incompatible (version trop récente)
        Feature::factory()->create([
            'key' => 'incompatible_feature',
            'mobile_version_min' => '2.0.0',
            'mobile_version_max' => null,
            'status' => 'active',
        ]);

        // Act
        $response = $this->getJson('/api/v1/features/compatible/1.0.0');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'mobile_version',
                    'total_features',
                    'features',
                ],
            ]);

        $features = $response->json('data.features');
        $featureKeys = array_column($features, 'key');

        $this->assertContains('compatible_feature', $featureKeys);
        $this->assertNotContains('incompatible_feature', $featureKeys);
    }

    /** @test */
    public function it_can_get_single_feature(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        $feature = Feature::factory()->create([
            'key' => 'test_feature',
            'permissions' => [], // Aucune permission requise
            'status' => 'active',
        ]);

        // Act
        $response = $this->getJson('/api/v1/features/test_feature');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'key',
                    'title',
                    'description',
                    'endpoint',
                    'methods',
                ],
            ]);

        $this->assertEquals('test_feature', $response->json('data.key'));
    }

    /** @test */
    public function it_returns_404_for_non_existent_feature(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        // Act
        $response = $this->getJson('/api/v1/features/non_existent_feature');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Feature not found',
            ]);
    }

    /** @test */
    public function it_filters_features_by_permissions(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        // Fonctionnalité sans permissions (accessible à tous)
        Feature::factory()->create([
            'key' => 'public_feature',
            'permissions' => [],
            'status' => 'active',
        ]);

        // Fonctionnalité avec permissions (non accessible)
        Feature::factory()->create([
            'key' => 'restricted_feature',
            'permissions' => ['admin.manage'],
            'status' => 'active',
        ]);

        // Act
        $response = $this->getJson('/api/v1/features/manifest');

        // Assert
        $response->assertStatus(200);

        $features = $response->json('data.features');
        $featureKeys = array_column($features, 'key');

        $this->assertContains('public_feature', $featureKeys);
        $this->assertNotContains('restricted_feature', $featureKeys);
    }

    /** @test */
    public function it_denies_access_to_restricted_feature(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        Feature::factory()->create([
            'key' => 'restricted_feature',
            'permissions' => ['admin.manage'],
            'status' => 'active',
        ]);

        // Act
        $response = $this->getJson('/api/v1/features/restricted_feature');

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions',
            ]);
    }

    /** @test */
    public function admin_can_access_statistics(): void
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        Feature::factory()->count(5)->create(['status' => 'active']);
        Feature::factory()->count(2)->create(['status' => 'deprecated']);

        // Act
        $response = $this->getJson('/api/v1/features/admin/statistics');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_features',
                    'active_features',
                    'inactive_features',
                    'by_status',
                    'cache_status',
                ],
            ]);

        $this->assertEquals(7, $response->json('data.total_features'));
        $this->assertEquals(5, $response->json('data.active_features'));
    }

    /** @test */
    public function non_admin_cannot_access_statistics(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        // Act
        $response = $this->getJson('/api/v1/features/admin/statistics');

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Admin access required',
            ]);
    }

    /** @test */
    public function admin_can_trigger_synchronization(): void
    {
        // Arrange
        Sanctum::actingAs($this->adminUser);

        // Act
        $response = $this->postJson('/api/v1/features/admin/synchronize');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'new',
                    'updated',
                    'removed',
                    'errors',
                ],
                'message',
            ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function non_admin_cannot_trigger_synchronization(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        // Act
        $response = $this->postJson('/api/v1/features/admin/synchronize');

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Admin access required',
            ]);
    }

    /** @test */
    public function it_handles_mobile_version_parameter(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        Feature::factory()->create([
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => '1.5.0',
            'status' => 'active',
        ]);

        Feature::factory()->create([
            'mobile_version_min' => '2.0.0',
            'mobile_version_max' => null,
            'status' => 'active',
        ]);

        // Act - Version 1.0.0 (devrait voir la première fonctionnalité)
        $response1 = $this->getJson('/api/v1/features/manifest?mobile_version=1.0.0');

        // Act - Version 2.0.0 (devrait voir la deuxième fonctionnalité)
        $response2 = $this->getJson('/api/v1/features/manifest?mobile_version=2.0.0');

        // Assert
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $this->assertEquals('1.0.0', $response1->json('data.mobile_version_target'));
        $this->assertEquals('2.0.0', $response2->json('data.mobile_version_target'));
    }

    /** @test */
    public function it_includes_user_context_in_manifest(): void
    {
        // Arrange
        Sanctum::actingAs($this->user);

        // Act
        $response = $this->getJson('/api/v1/features/manifest');

        // Assert
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($this->user->id, $data['user_id']);
        $this->assertEquals('employee', $data['user_role']);

        $meta = $response->json('meta');
        $this->assertEquals($this->user->id, $meta['generated_for_user']);
    }
}
