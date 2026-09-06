<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Issue #6850 (BC-23) — AIToolDefinition : contrat typé d'outil d'assistant.
 */
class AIToolDefinitionTest extends TestCase
{
    public function test_creates_valid_definition(): void
    {
        $definition = new AIToolDefinition(
            name: 'team_absences_recent',
            description: 'Absences récentes de l équipe (agrégat).',
            inputSchema: ['type' => 'object', 'properties' => ['period' => ['type' => 'string']]],
            sensitivity: AIToolSensitivity::Read,
            bc: 'BC-04',
        );

        self::assertSame('team_absences_recent', $definition->name);
        self::assertSame('read', $definition->sensitivity->value);
        self::assertSame('BC-04', $definition->bc);
        self::assertSame(1, $definition->version);
        self::assertTrue($definition->active);
    }

    public function test_rejects_invalid_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AIToolDefinition(name: 'NomInvalide!', description: 'x');
    }

    public function test_rejects_empty_description(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AIToolDefinition(name: 'valid_name', description: '');
    }

    public function test_rejects_version_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AIToolDefinition(name: 'valid_name', description: 'x', version: 0);
    }

    public function test_to_enrichment_exposes_metadata(): void
    {
        $definition = new AIToolDefinition(
            name: 'absence_decision',
            description: 'Décision sur une absence.',
            sensitivity: AIToolSensitivity::Write,
            bc: 'BC-06',
            version: 2,
        );

        $enrichment = $definition->toEnrichment();

        self::assertSame('write', $enrichment['sensitivity']);
        self::assertSame('BC-06', $enrichment['bc']);
        self::assertSame(2, $enrichment['tool_version']);
        self::assertArrayHasKey('input_schema', $enrichment);
        self::assertArrayHasKey('output_schema', $enrichment);
    }

    public function test_to_llm_format(): void
    {
        $definition = new AIToolDefinition(
            name: 'team_overview',
            description: 'Effectif de l équipe.',
            inputSchema: ['type' => 'object', 'properties' => []],
        );

        $format = $definition->toLLMFormat();

        self::assertSame('function', $format['type']);
        self::assertSame('team_overview', $format['function']['name']);
        self::assertSame('object', $format['function']['parameters']['type']);
    }
}
