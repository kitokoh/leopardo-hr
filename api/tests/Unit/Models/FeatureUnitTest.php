<?php

namespace Tests\Unit\Models;

use App\Models\Feature;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le modèle Feature sans base de données
 */
class FeatureUnitTest extends TestCase
{
    public function test_feature_model_has_correct_fillable_fields(): void
    {
        $feature = new Feature();
        
        $expectedFillable = [
            'company_id',
            'key',
            'title',
            'description',
            'endpoint',
            'http_methods',
            'parameters',
            'response_schema',
            'permissions',
            'mobile_version_min',
            'mobile_version_max',
            'api_version',
            'status',
            'metadata',
        ];
        
        $this->assertEquals($expectedFillable, $feature->getFillable());
    }

    public function test_feature_model_has_correct_casts(): void
    {
        $feature = new Feature();
        
        $expectedCasts = [
            'id' => 'int',
            'http_methods' => 'array',
            'parameters' => 'array',
            'response_schema' => 'array',
            'permissions' => 'array',
            'metadata' => 'array',
        ];
        
        $casts = $feature->getCasts();
        
        foreach ($expectedCasts as $field => $expectedCast) {
            $this->assertEquals($expectedCast, $casts[$field]);
        }
    }

    public function test_to_manifest_array_returns_correct_structure(): void
    {
        $feature = new Feature([
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
            'form_schema', 'list_schema', 'status', 'api_version'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $manifestArray);
        }

        $this->assertEquals('test_feature', $manifestArray['key']);
        $this->assertEquals('Test Feature', $manifestArray['title']);
        $this->assertEquals(['GET', 'POST'], $manifestArray['methods']);
        $this->assertEquals('list', $manifestArray['ui_type']);
    }

    public function test_to_manifest_array_handles_null_metadata(): void
    {
        $feature = new Feature([
            'key' => 'test_feature',
            'title' => 'Test Feature',
            'description' => 'A test feature',
            'endpoint' => '/api/v1/test',
            'http_methods' => ['GET'],
            'parameters' => [],
            'response_schema' => [],
            'permissions' => [],
            'mobile_version_min' => '1.0.0',
            'api_version' => '1.0.0',
            'status' => 'active',
            'metadata' => null,
        ]);

        $manifestArray = $feature->toManifestArray();

        $this->assertEquals('generic', $manifestArray['ui_type']);
        $this->assertNull($manifestArray['form_schema']);
        $this->assertNull($manifestArray['list_schema']);
    }

    public function test_to_manifest_array_handles_empty_metadata(): void
    {
        $feature = new Feature([
            'key' => 'test_feature',
            'title' => 'Test Feature',
            'description' => 'A test feature',
            'endpoint' => '/api/v1/test',
            'http_methods' => ['GET'],
            'parameters' => [],
            'response_schema' => [],
            'permissions' => [],
            'mobile_version_min' => '1.0.0',
            'api_version' => '1.0.0',
            'status' => 'active',
            'metadata' => [],
        ]);

        $manifestArray = $feature->toManifestArray();

        $this->assertEquals('generic', $manifestArray['ui_type']);
        $this->assertNull($manifestArray['form_schema']);
        $this->assertNull($manifestArray['list_schema']);
    }

    public function test_model_uses_correct_table_name(): void
    {
        $feature = new Feature();
        $this->assertEquals('features', $feature->getTable());
    }
}