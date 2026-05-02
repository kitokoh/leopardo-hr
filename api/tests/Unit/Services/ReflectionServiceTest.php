<?php

namespace Tests\Unit\Services;

use App\Services\ReflectionService;
use PHPUnit\Framework\TestCase;

class ReflectionServiceTest extends TestCase
{
    private ReflectionService $reflectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflectionService = new ReflectionService();
    }

    public function test_can_analyze_class(): void
    {
        $classInfo = $this->reflectionService->analyzeClass(ReflectionService::class);
        
        $this->assertIsArray($classInfo);
        $this->assertArrayHasKey('name', $classInfo);
        $this->assertArrayHasKey('short_name', $classInfo);
        $this->assertArrayHasKey('namespace', $classInfo);
        $this->assertArrayHasKey('methods', $classInfo);
        $this->assertArrayHasKey('attributes', $classInfo);
        
        $this->assertEquals(ReflectionService::class, $classInfo['name']);
        $this->assertEquals('ReflectionService', $classInfo['short_name']);
    }

    public function test_can_analyze_method(): void
    {
        $methodInfo = $this->reflectionService->analyzeMethod(
            ReflectionService::class,
            'analyzeClass'
        );
        
        $this->assertIsArray($methodInfo);
        $this->assertArrayHasKey('name', $methodInfo);
        $this->assertArrayHasKey('class', $methodInfo);
        $this->assertArrayHasKey('visibility', $methodInfo);
        $this->assertArrayHasKey('parameters', $methodInfo);
        $this->assertArrayHasKey('signature', $methodInfo);
        
        $this->assertEquals('analyzeClass', $methodInfo['name']);
        $this->assertEquals('public', $methodInfo['visibility']);
    }

    public function test_can_identify_api_controller(): void
    {
        // Test avec un contrôleur API valide
        $isApiController = $this->reflectionService->isApiController(
            'App\Http\Controllers\Api\V1\EmployeeController'
        );
        
        $this->assertTrue($isApiController);
        
        // Test avec une classe qui n'est pas un contrôleur API
        $isNotApiController = $this->reflectionService->isApiController(
            ReflectionService::class
        );
        
        $this->assertFalse($isNotApiController);
    }

    public function test_extracts_method_parameters(): void
    {
        $methodInfo = $this->reflectionService->analyzeMethod(
            ReflectionService::class,
            'analyzeMethod'
        );
        
        $this->assertArrayHasKey('parameters', $methodInfo);
        $this->assertIsArray($methodInfo['parameters']);
        $this->assertCount(2, $methodInfo['parameters']); // className et methodName
        
        $firstParam = $methodInfo['parameters'][0];
        $this->assertArrayHasKey('name', $firstParam);
        $this->assertArrayHasKey('type', $firstParam);
        $this->assertEquals('className', $firstParam['name']);
        $this->assertEquals('string', $firstParam['type']);
    }

    public function test_generates_method_signature(): void
    {
        $methodInfo = $this->reflectionService->analyzeMethod(
            ReflectionService::class,
            'analyzeClass'
        );
        
        $this->assertArrayHasKey('signature', $methodInfo);
        $this->assertStringContains('analyzeClass', $methodInfo['signature']);
        $this->assertStringContains('string $className', $methodInfo['signature']);
    }
}