<?php

namespace Tests\Unit\Models;

use App\Models\Feature;
use PHPUnit\Framework\TestCase;

class FeatureUnitTest extends TestCase
{
    public function test_feature_model_has_correct_fillable_fields()
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

    public function test_feature_model_has_correct_casts()
    {
        $feature = new Feature();

        $expectedCasts = [
            'http_methods' => 'array',
            'parameters' => 'array',
            'response_schema' => 'array',
            'permissions' => 'array',
            'metadata' => 'array',
        ];

        $casts = $feature->getCasts();

        foreach ($expectedCasts as $field => $cast) {
            $this->assertArrayHasKey($field, $casts);
            $this->assertEquals($cast, $casts[$field]);
        }
    }

    public function test_to_manifest_array_returns_correct_structure()
    {
        $feature = new Feature();

        $feature->key = 'test_feature';
        $feature->title = 'Test Feature';
        $feature->description = 'A test feature';
        $feature->endpoint = '/api/v1/test';
        $feature->http_methods = ['GET', 'POST'];
        $feature->parameters = ['param1' => 'value1'];
        $feature->response_schema = ['field1' => 'string'];
        $feature->permissions = ['test.view'];
        $feature->mobile_version_min = '1.0.0';
        $feature->mobile_version_max = '2.0.0';
        $feature->api_version = '1.1.0';
        $feature->status = 'active';
        $feature->metadata = [
            'ui_type' => 'list',
            'form_schema' => ['fields' => []],
            'list_schema' => ['columns' => []],
        ];

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
        $this->assertEquals(['fields' => []], $manifestArray['form_schema']);
    }

    public function test_to_manifest_array_handles_null_metadata()
    {
        $feature = new Feature();

        $feature->key = 'test_feature';
        $feature->title = 'Test Feature';
        $feature->description = 'A test feature';
        $feature->endpoint = '/api/v1/test';
        $feature->http_methods = ['GET'];
        $feature->parameters = [];
        $feature->response_schema = [];
        $feature->permissions = [];
        $feature->mobile_version_min = '1.0.0';
        $feature->mobile_version_max = null;
        $feature->api_version = '1.0.0';
        $feature->status = 'active';
        $feature->metadata = null;

        $manifestArray = $feature->toManifestArray();

        $this->assertEquals('generic', $manifestArray['ui_type']);
        $this->assertNull($manifestArray['form_schema']);
        $this->assertNull($manifestArray['list_schema']);
    }

    public function test_to_manifest_array_handles_empty_metadata()
    {
        $feature = new Feature();

        $feature->key = 'test_feature';
        $feature->title = 'Test Feature';
        $feature->description = 'A test feature';
        $feature->endpoint = '/api/v1/test';
        $feature->http_methods = ['GET'];
        $feature->parameters = [];
        $feature->response_schema = [];
        $feature->permissions = [];
        $feature->mobile_version_min = '1.0.0';
        $feature->mobile_version_max = null;
        $feature->api_version = '1.0.0';
        $feature->status = 'active';
        $feature->metadata = [];

        $manifestArray = $feature->toManifestArray();

        $this->assertEquals('generic', $manifestArray['ui_type']);
        $this->assertNull($manifestArray['form_schema']);
        $this->assertNull($manifestArray['list_schema']);
    }

    public function test_model_uses_correct_table_name()
    {
        $feature = new Feature;

        $this->assertEquals('features', $feature->getTable());
    }
}
