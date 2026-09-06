<?php

declare(strict_types=1);

namespace App\AI\Support;

use InvalidArgumentException;

/**
 * Définition typée d'un outil d'assistant — issue #6850 (BC-23, EPIC #6846).
 *
 * Contrat déclaratif standardisé (façon MCP) qu'un BC propriétaire déclare
 * pour exposer une action à l'assistant : nom unique, description (pour le
 * LLM), schémas d'entrée/sortie (JSON Schema), permission requise,
 * sensibilité, BC propriétaire. L'hôte (BC-23) découvre les définitions et
 * ne code rien en dur.
 *
 * Tranche A3-1 (additive) : la définition enrichit les outils existants du
 * registre (`ai_tool_registry`, ToolRegistry) sans changer leur comportement ;
 * le refactor complet de l'exposition et la déclaration par BC arrivent dans
 * la suite de l'issue (B-lots).
 */
final readonly class AIToolDefinition
{
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema = [],
        public array $outputSchema = [],
        public ?string $permission = null,
        public AIToolSensitivity $sensitivity = AIToolSensitivity::Read,
        public string $bc = '',
        public int $version = 1,
        public bool $active = true,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $this->name) !== 1) {
            throw new InvalidArgumentException(
                "AIToolDefinition name invalide : \"{$this->name}\" (attendu: snake_case, 2-64 chars)."
            );
        }
        if ($this->description === '') {
            throw new InvalidArgumentException('AIToolDefinition description obligatoire.');
        }
        if ($this->version < 1) {
            throw new InvalidArgumentException('AIToolDefinition version >= 1.');
        }
        if (! is_array($this->inputSchema)) {
            throw new InvalidArgumentException('AIToolDefinition inputSchema doit être un tableau.');
        }
    }

    /**
     * Métadonnées additionnelles pour l'enrichissement du registre existant.
     *
     * @return array<string, mixed>
     */
    public function toEnrichment(): array
    {
        return [
            'sensitivity' => $this->sensitivity->value,
            'bc' => $this->bc,
            'tool_version' => $this->version,
            'input_schema' => $this->inputSchema,
            'output_schema' => $this->outputSchema,
        ];
    }

    /**
     * Format LLM (identique à ToolRegistry::getToolsAsLLMFormat).
     *
     * @return array<string, mixed>
     */
    public function toLLMFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->inputSchema !== []
                    ? $this->inputSchema
                    : ['type' => 'object', 'properties' => new \stdClass],
            ],
        ];
    }
}
