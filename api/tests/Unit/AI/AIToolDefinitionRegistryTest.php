<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolDefinitionRegistry;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Issue #6850 (BC-23) — AIToolDefinitionRegistry : collecteur des outils
 * déclarés par les BC (découverte par l'hôte, anti-doublon).
 */
class AIToolDefinitionRegistryTest extends TestCase
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

    public function test_register_and_find(): void
    {
        $definition = new AIToolDefinition(name: 'tool_a', description: 'A', bc: 'BC-04');

        AIToolDefinitionRegistry::register($definition);

        self::assertTrue(AIToolDefinitionRegistry::has('tool_a'));
        self::assertSame($definition, AIToolDefinitionRegistry::find('tool_a'));
        self::assertNull(AIToolDefinitionRegistry::find('missing'));
        self::assertCount(1, AIToolDefinitionRegistry::all());
    }

    public function test_for_bc_filters_by_bounded_context(): void
    {
        AIToolDefinitionRegistry::register(new AIToolDefinition(name: 'hr_a', description: 'A', bc: 'BC-04'));
        AIToolDefinitionRegistry::register(new AIToolDefinition(name: 'hr_b', description: 'B', bc: 'BC-04'));
        AIToolDefinitionRegistry::register(new AIToolDefinition(name: 'pay_a', description: 'C', bc: 'BC-07'));

        self::assertCount(2, AIToolDefinitionRegistry::forBc('BC-04'));
        self::assertCount(1, AIToolDefinitionRegistry::forBc('BC-07'));
        self::assertCount(0, AIToolDefinitionRegistry::forBc('BC-99'));
    }

    public function test_duplicate_name_is_rejected(): void
    {
        AIToolDefinitionRegistry::register(new AIToolDefinition(name: 'dup', description: 'A'));

        $this->expectException(InvalidArgumentException::class);

        AIToolDefinitionRegistry::register(new AIToolDefinition(name: 'dup', description: 'B'));
    }
}
