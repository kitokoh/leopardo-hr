<?php

namespace Tests\Unit\Models;

use App\Models\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_a_feature_with_all_required_fields()
    {
        $featureData = [
            'key' => 'employee_management',
            'title' => 'Gestion des Employés',
            'description' => 'Module de gestion complète des employés',
            'endpoint' => '/api/v1/employees',
            'http_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'parameters' => [
                'list' => [
                    'page' => ['type' => 'integer', 'required' => false],
                    'per_page' => ['type' => 'integer', 'required' => false],
                ],
            ],
            'response_schema' => [
                'employee' => [
                    'id' => 'integer',
                    'first_name' => 'string',
                    'last_name' => 'string',
                    'email' => 'string',
                ],
            ],
            'permissions' => ['employees.view', 'employees.create'],
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => null,
            'api_version' => '1.2.0',
            'status' => 'active',
            'metadata' => [
                'ui_type' => 'list',
                'form_schema' => [
                    'fields' => [
                        [
                            'name' => 'first_name',
                            'type' => 'text',
                            'label' => 'Prénom',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ];

        $feature = Feature::create($featureData);

        $this->assertInstanceOf(Feature::class, $feature);
        $this->assertEquals('employee_management', $feature->key);
        $this->assertEquals('Gestion des Employés', $feature->title);
        $this->assertEquals(['GET', 'POST', 'PUT', 'DELETE'], $feature->http_methods);
        $this->assertEquals('active', $feature->status);
    }

    /** @test */
    public function it_casts_json_fields_correctly()
    {
        $feature = Feature::factory()->create([
            'http_methods' => ['GET', 'POST'],
            'parameters' => ['test' => 'value'],
            'response_schema' => ['field' => 'type'],
            'permissions' => ['permission1', 'permission2'],
            'metadata' => ['ui_type' => 'form'],
        ]);

        $this->assertIsArray($feature->http_methods);
        $this->assertIsArray($feature->parameters);
        $this->assertIsArray($feature->response_schema);
        $this->assertIsArray($feature->permissions);
        $this->assertIsArray($feature->metadata);

        $this->assertEquals(['GET', 'POST'], $feature->http_methods);
        $this->assertEquals(['permission1', 'permission2'], $feature->permissions);
    }

    /** @test */
    public function it_generates_manifest_array_correctly()
    {
        $feature = Feature::factory()->create([
            'key' => 'test_feature',
            'title' => 'Test Feature',
            'description' => 'A test feature',
            'endpoint' => '/api/v1/test',
            'http_methods' => ['GET', 'POST'],
            'parameters' => ['param1' => 'value1'],
            'response_schema' => ['field1' => 'string'],
            'permissions' => ['test.view'],
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => '2.0.0',
            'api_version' => '1.1.0',
            'status' => 'active',
            'metadata' => [
                'ui_type' => 'list',
                'form_schema' => ['fields' => []],
                'list_schema' => ['columns' => []],
            ],
        ]);

        $manifestArray = $feature->toManifestArray();

        $expectedKeys = [
            'key', 'title', 'description', 'endpoint', 'methods',
            'parameters', 'response_schema', 'permissions',
            'mobile_version_min', 'mobile_version_max', 'ui_type',
            'form_schema', 'list_schema', 'status', 'api_version',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $manifestArray);
        }

        $this->assertEquals('test_feature', $manifestArray['key']);
        $this->assertEquals('Test Feature', $manifestArray['title']);
        $this->assertEquals(['GET', 'POST'], $manifestArray['methods']);
        $this->assertEquals('list', $manifestArray['ui_type']);
    }

    /** @test */
    public function it_handles_null_metadata_gracefully_in_manifest()
    {
        $feature = Feature::factory()->create([
            'metadata' => null,
        ]);

        $manifestArray = $feature->toManifestArray();

        $this->assertEquals('generic', $manifestArray['ui_type']);
        $this->assertNull($manifestArray['form_schema']);
        $this->assertNull($manifestArray['list_schema']);
    }

    /** @test */
    public function it_scopes_active_features_correctly()
    {
        Feature::factory()->create(['status' => 'active']);
        Feature::factory()->create(['status' => 'deprecated']);
        Feature::factory()->create(['status' => 'removed']);

        $activeFeatures = Feature::active()->get();

        $this->assertCount(1, $activeFeatures);
        $this->assertEquals('active', $activeFeatures->first()->status);
    }

    /** @test */
    public function it_scopes_compatible_features_correctly()
    {
        // Fonctionnalité compatible (version min 1.0.0, pas de max)
        Feature::factory()->create([
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => null,
        ]);

        // Fonctionnalité compatible (version min 1.0.0, max 2.0.0)
        Feature::factory()->create([
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => '2.0.0',
        ]);

        // Fonctionnalité incompatible (version min trop élevée)
        Feature::factory()->create([
            'mobile_version_min' => '2.0.0',
            'mobile_version_max' => null,
        ]);

        // Fonctionnalité incompatible (version max trop basse)
        Feature::factory()->create([
            'mobile_version_min' => '1.0.0',
            'mobile_version_max' => '1.1.0',
        ]);

        $compatibleFeatures = Feature::compatibleWith('1.5.0')->get();

        $this->assertCount(2, $compatibleFeatures);
    }

    /** @test */
    public function it_scopes_features_by_api_version_correctly()
    {
        Feature::factory()->create(['api_version' => '1.0.0']);
        Feature::factory()->create(['api_version' => '1.1.0']);
        Feature::factory()->create(['api_version' => '1.1.0']);

        $v11Features = Feature::forApiVersion('1.1.0')->get();

        $this->assertCount(2, $v11Features);
        $v11Features->each(function ($feature) {
            $this->assertEquals('1.1.0', $feature->api_version);
        });
    }

    /** @test */
    public function it_belongs_to_company_when_company_id_is_set()
    {
        $feature = Feature::factory()->create([
            'company_id' => 'test-company-uuid',
        ]);

        $this->assertEquals('test-company-uuid', $feature->company_id);
    }

    /** @test */
    public function it_can_have_null_company_id_for_global_features()
    {
        $feature = Feature::factory()->create([
            'company_id' => null,
        ]);

        $this->assertNull($feature->company_id);
    }
}
