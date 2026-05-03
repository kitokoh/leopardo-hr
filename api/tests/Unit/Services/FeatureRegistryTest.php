<?php

namespace Tests\Unit\Services;

use App\Contracts\FeatureDetectorInterface;
use App\Exceptions\FeatureSynchronizationException;
use App\Models\Feature;
use App\Services\FeatureRegistry;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests unitaires pour le service FeatureRegistry
 */
class FeatureRegistryTest extends TestCase
{
    use RefreshTenantDatabase;

    private FeatureRegistry $registry;

    private FeatureDetectorInterface $mockDetector;

    private CacheManager $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('features')) {
            Schema::create('features', function (Blueprint $table): void {
                $table->increments('id');
                $table->uuid('company_id')->nullable()->index();
                $table->string('key', 100)->unique();
                $table->string('title', 200);
                $table->text('description');
                $table->string('endpoint', 500);
                $table->json('http_methods');
                $table->json('parameters');
                $table->json('response_schema');
                $table->json('permissions');
                $table->json('metadata')->nullable();
                $table->string('mobile_version_min', 20);
                $table->string('mobile_version_max', 20)->nullable();
                $table->string('api_version', 20);
                $table->string('status', 20)->default('active');
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });
        }

        $this->mockDetector = Mockery::mock(FeatureDetectorInterface::class);
        $this->mockCache = Mockery::mock(CacheManager::class);

        $this->registry = new FeatureRegistry(
            $this->mockDetector,
            $this->mockCache
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_register_a_new_feature(): void
    {
        // Arrange
        $featureData = [
            'key' => 'test_feature',
            'title' => 'Test Feature',
            'description' => 'A test feature',
            'endpoint' => '/api/v1/test',
            'http_methods' => ['GET', 'POST'],
            'parameters' => [],
            'response_schema' => [],
            'permissions' => ['test.view'],
            'mobile_version_min' => '1.0.0',
            'api_version' => 'v1',
            'status' => 'active',
            'metadata' => [],
        ];

        $feature = new Feature($featureData);

        // Mock cache invalidation
        $this->mockCache->shouldReceive('forget')->andReturn(true);
        $this->mockCache->shouldReceive('tags')->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->andReturn(true);

        // Act
        $this->registry->registerFeature($feature);

        // Assert
        $this->assertDatabaseHas('features', [
            'key' => 'test_feature',
            'title' => 'Test Feature',
        ]);
    }

    /** @test */
    public function it_can_update_existing_feature_when_registering(): void
    {
        // Arrange
        $existingFeature = Feature::factory()->create([
            'key' => 'existing_feature',
            'title' => 'Old Title',
        ]);

        $updatedFeature = new Feature([
            'key' => 'existing_feature',
            'title' => 'New Title',
            'description' => 'Updated description',
            'endpoint' => '/api/v1/updated',
            'http_methods' => ['GET'],
            'parameters' => [],
            'response_schema' => [],
            'permissions' => ['test.view'],
            'mobile_version_min' => '1.0.0',
            'api_version' => 'v1',
            'status' => 'active',
            'metadata' => [],
        ]);

        // Mock cache invalidation
        $this->mockCache->shouldReceive('forget')->andReturn(true);
        $this->mockCache->shouldReceive('tags')->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->andReturn(true);

        // Act
        $this->registry->registerFeature($updatedFeature);

        // Assert
        $this->assertDatabaseHas('features', [
            'key' => 'existing_feature',
            'title' => 'New Title',
        ]);

        $this->assertDatabaseMissing('features', [
            'key' => 'existing_feature',
            'title' => 'Old Title',
        ]);
    }

    /** @test */
    public function it_can_get_features_with_caching(): void
    {
        // Arrange
        $features = Feature::factory()->count(3)->create();
        $expectedCollection = collect($features);

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->andReturn($expectedCollection);

        // Act
        $result = $this->registry->getFeatures();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
    }

    /** @test */
    public function it_can_get_features_by_api_version(): void
    {
        // Arrange
        Feature::factory()->create(['api_version' => 'v1']);
        Feature::factory()->create(['api_version' => 'v2']);

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->registry->getFeatures('v1');

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
    }

    /** @test */
    public function it_can_get_single_feature_with_caching(): void
    {
        // Arrange
        $feature = Feature::factory()->create(['key' => 'test_feature']);

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->andReturn($feature);

        // Act
        $result = $this->registry->getFeature('test_feature');

        // Assert
        $this->assertInstanceOf(Feature::class, $result);
        $this->assertEquals('test_feature', $result->key);
    }

    /** @test */
    public function it_returns_null_for_non_existent_feature(): void
    {
        // Arrange
        $this->mockCache->shouldReceive('remember')
            ->once()
            ->andReturn(null);

        // Act
        $result = $this->registry->getFeature('non_existent');

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function it_can_update_feature_metadata(): void
    {
        // Arrange
        $feature = Feature::factory()->create([
            'key' => 'test_feature',
            'metadata' => ['old_key' => 'old_value'],
        ]);

        $newMetadata = ['new_key' => 'new_value'];

        // Mock cache invalidation
        $this->mockCache->shouldReceive('forget')->andReturn(true);
        $this->mockCache->shouldReceive('tags')->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->andReturn(true);

        // Act
        $this->registry->updateFeature('test_feature', $newMetadata);

        // Assert
        $feature->refresh();
        $this->assertEquals([
            'old_key' => 'old_value',
            'new_key' => 'new_value',
        ], $feature->metadata);
    }

    /** @test */
    public function it_throws_exception_when_updating_non_existent_feature(): void
    {
        // Arrange
        $this->expectException(FeatureSynchronizationException::class);
        $this->expectExceptionMessage('Feature non_existent not found for update');

        // Act
        $this->registry->updateFeature('non_existent', ['key' => 'value']);
    }

    /** @test */
    public function it_can_remove_feature(): void
    {
        // Arrange
        $feature = Feature::factory()->create(['key' => 'test_feature']);

        // Mock cache invalidation
        $this->mockCache->shouldReceive('forget')->andReturn(true);
        $this->mockCache->shouldReceive('tags')->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->andReturn(true);

        // Act
        $this->registry->removeFeature('test_feature');

        // Assert
        $this->assertDatabaseMissing('features', ['key' => 'test_feature']);
    }

    /** @test */
    public function it_can_generate_manifest(): void
    {
        // Arrange
        $features = Feature::factory()->count(2)->create([
            'mobile_version_min' => '1.0.0',
            'status' => 'active',
        ]);

        $this->mockCache->shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $manifest = $this->registry->getManifest('1.0.0');

        // Assert
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('generated_at', $manifest);
        $this->assertArrayHasKey('features', $manifest);
        $this->assertArrayHasKey('total_features', $manifest);
    }

    /** @test */
    public function it_can_get_compatible_features(): void
    {
        // Arrange
        Feature::factory()->create([
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => null,
            'status' => 'active',
        ]);

        Feature::factory()->create([
            'mobile_version_min' => '2.0.0',
            'mobile_version_max' => null,
            'status' => 'active',
        ]);

        $this->mockCache->shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->registry->getCompatibleFeatures('1.5.0');

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
    }

    /** @test */
    public function it_can_check_if_feature_exists(): void
    {
        // Arrange
        $feature = Feature::factory()->create(['key' => 'existing_feature']);

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->andReturn($feature);

        // Act & Assert
        $this->assertTrue($this->registry->hasFeature('existing_feature'));
    }

    /** @test */
    public function it_returns_false_for_non_existent_feature_check(): void
    {
        // Arrange
        $this->mockCache->shouldReceive('remember')
            ->once()
            ->andReturn(null);

        // Act & Assert
        $this->assertFalse($this->registry->hasFeature('non_existent'));
    }

    /** @test */
    public function it_can_invalidate_cache(): void
    {
        // Arrange
        $this->mockCache->shouldReceive('forget')->andReturn(true);
        $this->mockCache->shouldReceive('tags')->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->andReturn(true);

        // Act & Assert - Should not throw exception
        $this->registry->invalidateCache();
        $this->registry->invalidateCache('specific_key');

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    /** @test */
    public function it_can_synchronize_with_detector(): void
    {
        // Arrange
        $newFeatures = collect([
            [
                'key' => 'new_feature',
                'title' => 'New Feature',
                'description' => 'A new feature',
                'endpoint' => '/api/v1/new',
                'http_methods' => ['GET'],
                'parameters' => [],
                'response_schema' => [],
                'permissions' => ['new.view'],
                'mobile_version_min' => '1.0.0',
                'api_version' => 'v1',
                'status' => 'active',
                'metadata' => [],
            ],
        ]);

        $changes = collect([]);

        $this->mockDetector->shouldReceive('detectNewFeatures')
            ->once()
            ->andReturn($newFeatures);

        $this->mockDetector->shouldReceive('detectChanges')
            ->once()
            ->andReturn($changes);

        // Mock cache invalidation
        $this->mockCache->shouldReceive('forget')->andReturn(true);
        $this->mockCache->shouldReceive('tags')->andReturnSelf();
        $this->mockCache->shouldReceive('flush')->andReturn(true);

        // Act
        $result = $this->registry->synchronize();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('new', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('removed', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEquals(1, $result['new']);
    }

    /** @test */
    public function it_can_get_statistics(): void
    {
        // Arrange
        Feature::factory()->count(5)->create(['status' => 'active']);
        Feature::factory()->count(2)->create(['status' => 'inactive']);

        $this->mockCache->shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->mockCache->shouldReceive('get')
            ->andReturn(null);

        $this->mockCache->shouldReceive('has')
            ->andReturn(false);

        // Act
        $stats = $this->registry->getStatistics();

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_features', $stats);
        $this->assertArrayHasKey('active_features', $stats);
        $this->assertArrayHasKey('inactive_features', $stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertEquals(7, $stats['total_features']);
        $this->assertEquals(5, $stats['active_features']);
        $this->assertEquals(2, $stats['inactive_features']);
    }
}
