<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolDefinitionRegistry;
use App\AI\Support\AIToolSensitivity;
use App\AI\ToolRegistry;
use ReflectionClass;
use Tests\TestCase;

/**
 * Issue #6850 (BC-23) — enrichissement du ToolRegistry existant par les
 * AIToolDefinition déclarées (tranche additive : aucun changement de
 * comportement, les métadonnées sont ajoutées sans remplacer les champs).
 */
class ToolRegistryEnrichmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AIToolDefinitionRegistry::reset();
    }

    protected function tearDown(): void
    {
        AIToolDefinitionRegistry::reset();
        parent::tearDown();
    }

    public function test_find_tool_is_enriched_by_declared_definition(): void
    {
        $registry = new ToolRegistry;
        $this->injectTool($registry, 'absence_decision', ['description' => 'DB description']);

        AIToolDefinitionRegistry::register(new AIToolDefinition(
            name: 'absence_decision',
            description: 'DB description',
            sensitivity: AIToolSensitivity::Write,
            bc: 'BC-06',
            version: 2,
        ));

        $tool = $registry->findTool('absence_decision');

        self::assertNotNull($tool);
        self::assertSame('write', $tool['sensitivity']);
        self::assertSame('BC-06', $tool['bc']);
        self::assertSame(2, $tool['tool_version']);
        self::assertSame('DB description', $tool['description']);
    }

    public function test_tool_without_definition_is_unchanged(): void
    {
        $registry = new ToolRegistry;
        $this->injectTool($registry, 'plain_tool', ['description' => 'sans définition']);

        $tool = $registry->findTool('plain_tool');

        self::assertNotNull($tool);
        self::assertArrayNotHasKey('sensitivity', $tool);
        self::assertSame('sans définition', $tool['description']);
    }

    /**
     * Injecte une entrée outil sans passer par la table ai_tool_registry
     * (tests unitaires sans base).
     *
     * @param  array<string, mixed>  $extra
     */
    private function injectTool(ToolRegistry $registry, string $name, array $extra = []): void
    {
        $property = (new ReflectionClass(ToolRegistry::class))->getProperty('tools');
        $tools = $property->getValue($registry);
        $tools[$name] = array_merge([
            'id' => 1,
            'name' => $name,
            'description' => $extra['description'] ?? 'description',
            'parameters' => [],
            'required_permissions' => [],
            'required_role' => 'manager',
            'module' => 'hr',
            'active' => true,
        ], $extra);
        $property->setValue($registry, $tools);
    }
}
