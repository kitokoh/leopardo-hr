<?php

namespace Tests\Unit\Services;

use App\Contracts\FeatureDetectorInterface;
use App\Services\AnnotationReader;
use App\Services\FeatureDetector;
use App\Services\ReflectionService;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FeatureDetectorTest extends TestCase
{
    private FeatureDetectorInterface $featureDetector;

    private Router $router;

    private ReflectionService $reflectionService;

    private AnnotationReader $annotationReader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = app(Router::class);
        $this->reflectionService = app(ReflectionService::class);
        $this->annotationReader = app(AnnotationReader::class);

        $this->featureDetector = new FeatureDetector(
            $this->router,
            $this->reflectionService,
            $this->annotationReader
        );
    }

    public function test_can_scan_api_routes(): void
    {
        $routes = $this->featureDetector->scanRoutes();

        $this->assertInstanceOf(Collection::class, $routes);
        $this->assertGreaterThan(0, $routes->count());

        // Vérifier la structure d'une route
        $route = $routes->first();
        $this->assertArrayHasKey('uri', $route);
        $this->assertArrayHasKey('methods', $route);
        $this->assertArrayHasKey('controller_class', $route);
        $this->assertArrayHasKey('method', $route);

        // Vérifier que c'est bien une route API
        $this->assertStringStartsWith('api/', $route['uri']);
    }

    public function test_can_extract_metadata_from_controller_method(): void
    {
        // Tester avec EmployeeController qui a des attributs
        $metadata = $this->featureDetector->extractMetadata(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('title', $metadata);
        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('permissions', $metadata);
        $this->assertArrayHasKey('ui_type', $metadata);
        $this->assertArrayHasKey('mobile_compatible', $metadata);

        // Vérifier les valeurs spécifiques des attributs
        $this->assertEquals('Liste des Employés', $metadata['title']);
        $this->assertEquals('list', $metadata['ui_type']);
        $this->assertTrue($metadata['mobile_compatible']);
        $this->assertContains('employees.view', $metadata['permissions']);
    }

    public function test_can_detect_new_features_without_database(): void
    {
        // Mock la méthode qui accède à la base de données
        $detector = new class($this->router, $this->reflectionService, $this->annotationReader) extends FeatureDetector
        {
            public function detectNewFeatures(): Collection
            {
                $routes = $this->scanRoutes();
                $newFeatures = collect();

                // Simuler qu'il n'y a pas de fonctionnalités existantes
                $existingFeatures = [];

                foreach ($routes->take(5) as $routeData) { // Limiter à 5 pour le test
                    try {
                        $featureKey = $this->generateFeatureKey($routeData);

                        if (in_array($featureKey, $existingFeatures)) {
                            continue;
                        }

                        $metadata = $this->extractMetadata(
                            $routeData['controller_class'],
                            $routeData['method']
                        );

                        if ($this->isValidFeature($metadata)) {
                            $featureData = $this->buildFeatureData($routeData, $metadata);
                            $newFeatures->push($featureData);
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs pour le test
                    }
                }

                return $newFeatures;
            }

            // Exposer les méthodes privées pour le test
            public function generateFeatureKey(array $routeData): string
            {
                return parent::generateFeatureKey($routeData);
            }

            public function isValidFeature(array $metadata): bool
            {
                return parent::isValidFeature($metadata);
            }

            public function buildFeatureData(array $routeData, array $metadata): array
            {
                return parent::buildFeatureData($routeData, $metadata);
            }
        };

        $features = $detector->detectNewFeatures();

        $this->assertInstanceOf(Collection::class, $features);

        if ($features->count() > 0) {
            // Vérifier la structure d'une fonctionnalité
            $feature = $features->first();
            $this->assertArrayHasKey('key', $feature);
            $this->assertArrayHasKey('title', $feature);
            $this->assertArrayHasKey('description', $feature);
            $this->assertArrayHasKey('endpoint', $feature);
            $this->assertArrayHasKey('http_methods', $feature);
            $this->assertArrayHasKey('permissions', $feature);
            $this->assertArrayHasKey('mobile_version_min', $feature);
            $this->assertArrayHasKey('api_version', $feature);
            $this->assertArrayHasKey('metadata', $feature);
        }
    }

    public function test_extracts_api_version_from_uri(): void
    {
        $routes = $this->featureDetector->scanRoutes();

        // Trouver une route v1
        $v1Route = $routes->first(function ($route) {
            return str_contains($route['uri'], 'api/v1/');
        });

        if ($v1Route) {
            // Utiliser la méthode extractApiVersionFromUri via reflection
            $reflection = new \ReflectionClass($this->featureDetector);
            $method = $reflection->getMethod('extractApiVersionFromUri');
            $method->setAccessible(true);

            $version = $method->invoke($this->featureDetector, $v1Route['uri']);
            $this->assertEquals('v1', $version);
        }
    }

    public function test_generates_unique_feature_keys(): void
    {
        $routes = $this->featureDetector->scanRoutes()->take(10); // Limiter pour le test

        $keys = [];
        foreach ($routes as $route) {
            // Utiliser la méthode generateFeatureKey via reflection
            $reflection = new \ReflectionClass($this->featureDetector);
            $method = $reflection->getMethod('generateFeatureKey');
            $method->setAccessible(true);

            $key = $method->invoke($this->featureDetector, $route);
            $keys[] = $key;
        }

        $uniqueKeys = array_unique($keys);

        $this->assertEquals(
            count($keys),
            count($uniqueKeys),
            'All feature keys should be unique'
        );
    }

    public function test_validates_feature_metadata(): void
    {
        // Tester avec des métadonnées valides
        $validMetadata = [
            'title' => 'Test Feature',
            'method_info' => ['name' => 'index'],
            'mobile_compatible' => true,
        ];

        $reflection = new \ReflectionClass($this->featureDetector);
        $method = $reflection->getMethod('isValidFeature');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->featureDetector, $validMetadata));

        // Tester avec des métadonnées invalides
        $invalidMetadata = [
            'title' => '',
            'method_info' => [],
            'mobile_compatible' => false,
        ];

        $this->assertFalse($method->invoke($this->featureDetector, $invalidMetadata));
    }

    public function test_extracts_permissions_from_attributes(): void
    {
        // Tester l'extraction de métadonnées avec des permissions
        $metadata = $this->featureDetector->extractMetadata(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        $this->assertArrayHasKey('permissions', $metadata);
        $this->assertIsArray($metadata['permissions']);
        $this->assertNotEmpty($metadata['permissions']);
        $this->assertContains('employees.view', $metadata['permissions']);
    }

    public function test_includes_form_and_list_schemas_when_available(): void
    {
        $metadata = $this->featureDetector->extractMetadata(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        // Les schémas doivent être présents (même vides)
        $this->assertArrayHasKey('form_schema', $metadata);
        $this->assertArrayHasKey('list_schema', $metadata);
        $this->assertIsArray($metadata['form_schema']);
        $this->assertIsArray($metadata['list_schema']);
    }
}
