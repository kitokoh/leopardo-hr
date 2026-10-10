<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\ApiEndpointRegistryInterface;
use App\Core\Feature\Infrastructure\Services\ApiEndpointRegistry;
use App\Contracts\FeatureDetectorInterface;
use App\Modules\Billing\Domain\Models\Feature;
use Illuminate\Cache\CacheManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_endpoint_registry_implements_interface(): void
    {
        $registry = app(ApiEndpointRegistryInterface::class);
        $this->assertInstanceOf(ApiEndpointRegistry::class, $registry);
    }

    public function test_can_retrieve_manifest(): void
    {
        /** @var ApiEndpointRegistryInterface $registry */
        $registry = app(ApiEndpointRegistryInterface::class);
        $manifest = $registry->getManifest();

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('total_features', $manifest);
        $this->assertArrayHasKey('features', $manifest);
    }
}
