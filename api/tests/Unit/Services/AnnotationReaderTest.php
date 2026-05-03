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
        $this->annotationReader = new AnnotationReader();
    }

    public function test_can_extract_method_annotations(): void
    {
        // Tester avec EmployeeController qui a des attributs
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
        
        // Vérifier les valeurs extraites des attributs
        $this->assertEquals('Liste des Employés', $annotations['title']);
        $this->assertEquals('list', $annotations['ui_type']);
        $this->assertTrue($annotations['mobile_compatible']);
        $this->assertContains('employees.view', $annotations['permissions']);
    }

    public function test_generates_title_from_method_name(): void
    {
        $title = $this->annotationReader->generateTitleFromMethod('index');
        $this->assertEquals('Liste des éléments', $title);
        
        $title = $this->annotationReader->generateTitleFromMethod('show');
        $this->assertEquals('Afficher un élément', $title);
        
        $title = $this->annotationReader->generateTitleFromMethod('store');
        $this->assertEquals('Créer un nouvel élément', $title);
        
        // Test avec une méthode personnalisée
        $title = $this->annotationReader->generateTitleFromMethod('getUserProfile');
        $this->assertEquals('Get User Profile', $title);
    }

    public function test_generates_description_from_method_name(): void
    {
        $description = $this->annotationReader->generateDescriptionFromMethod(
            'index',
            'App\Http\Controllers\Api\V1\EmployeeController'
        );
        
        $this->assertStringContainsString('Récupère la liste', $description);
        $this->assertStringContainsString('employee', $description);
        
        $description = $this->annotationReader->generateDescriptionFromMethod(
            'store',
            'App\Http\Controllers\Api\V1\EmployeeController'
        );
        
        $this->assertStringContains('Crée un nouveau', $description);
        $this->assertStringContains('employee', $description);
    }

    public function test_extracts_all_apifeature_attributes(): void
    {
        $annotations = $this->annotationReader->extractMethodAnnotations(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );
        
        // Vérifier que tous les champs de ApiFeature sont extraits
        $this->assertArrayHasKey('mobile_version_min', $annotations);
        $this->assertArrayHasKey('mobile_version_max', $annotations);
        $this->assertArrayHasKey('form_schema', $annotations);
        $this->assertArrayHasKey('list_schema', $annotations);
        
        // Ces champs doivent être des tableaux même s'ils sont vides
        $this->assertIsArray($annotations['form_schema']);
        $this->assertIsArray($annotations['list_schema']);
    }

    public function test_handles_multiple_permission_formats(): void
    {
        // Cette méthode teste la gestion des différents formats de permissions
        // mais nécessiterait un contrôleur de test avec différents formats
        
        // Pour l'instant, on teste juste que les permissions sont bien des tableaux
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