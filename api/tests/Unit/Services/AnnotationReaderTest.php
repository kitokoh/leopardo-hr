<?php

namespace Tests\Unit\Services;

use App\Services\AnnotationReader;
use PHPUnit\Framework\TestCase;

class AnnotationReaderTest extends TestCase
{
    private AnnotationReader $annotationReader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->annotationReader = new AnnotationReader;
    }

    public function test_can_extract_method_annotations(): void
    {
        $annotations = $this->annotationReader->extractMethodAnnotations(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        $this->assertIsArray($annotations);
        $this->assertArrayHasKey('title', $annotations);
        $this->assertArrayHasKey('description', $annotations);
        $this->assertArrayHasKey('permissions', $annotations);
        $this->assertArrayHasKey('ui_type', $annotations);
        $this->assertArrayHasKey('mobile_compatible', $annotations);
        $this->assertArrayHasKey('form_schema', $annotations);
        $this->assertArrayHasKey('list_schema', $annotations);

        $this->assertEquals('Liste des EmployÃ©s', $annotations['title']);
        $this->assertEquals('list', $annotations['ui_type']);
        $this->assertTrue($annotations['mobile_compatible']);
        $this->assertContains('employees.view', $annotations['permissions']);
    }

    public function test_generates_title_from_method_name(): void
    {
        $title = $this->annotationReader->generateTitleFromMethod('index');
        $this->assertEquals('Liste des Ã©lÃ©ments', $title);

        $title = $this->annotationReader->generateTitleFromMethod('show');
        $this->assertEquals('Afficher un Ã©lÃ©ment', $title);

        $title = $this->annotationReader->generateTitleFromMethod('store');
        $this->assertEquals('CrÃ©er un nouvel Ã©lÃ©ment', $title);

        $title = $this->annotationReader->generateTitleFromMethod('getUserProfile');
        $this->assertEquals('Get User Profile', $title);
    }

    public function test_generates_description_from_method_name(): void
    {
        $description = $this->annotationReader->generateDescriptionFromMethod(
            'index',
            'App\Http\Controllers\Api\V1\EmployeeController'
        );

        $this->assertStringContainsString('RÃ©cupÃ¨re la liste', $description);
        $this->assertStringContainsString('employee', $description);

        $description = $this->annotationReader->generateDescriptionFromMethod(
            'store',
            'App\Http\Controllers\Api\V1\EmployeeController'
        );
        $this->assertStringContainsString('CrÃ©e un nouveau', $description);
        $this->assertStringContainsString('employee', $description);
    }

    public function test_extracts_all_apifeature_attributes(): void
    {
        $annotations = $this->annotationReader->extractMethodAnnotations(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        $this->assertArrayHasKey('mobile_version_min', $annotations);
        $this->assertArrayHasKey('mobile_version_max', $annotations);
        $this->assertArrayHasKey('form_schema', $annotations);
        $this->assertArrayHasKey('list_schema', $annotations);

        $this->assertIsArray($annotations['form_schema']);
        $this->assertIsArray($annotations['list_schema']);
    }

    public function test_handles_multiple_permission_formats(): void
    {
        $annotations = $this->annotationReader->extractMethodAnnotations(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        $this->assertIsArray($annotations['permissions']);
        $this->assertNotEmpty($annotations['permissions']);
    }

    public function test_returns_empty_array_for_nonexistent_method(): void
    {
        $annotations = $this->annotationReader->extractMethodAnnotations(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'nonExistentMethod'
        );

        $this->assertIsArray($annotations);
        $this->assertEmpty($annotations);
    }

    public function test_returns_empty_array_for_nonexistent_class(): void
    {
        $annotations = $this->annotationReader->extractMethodAnnotations(
            'NonExistentClass',
            'index'
        );

        $this->assertIsArray($annotations);
        $this->assertEmpty($annotations);
    }
}
