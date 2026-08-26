<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\IntentEngine;
use App\AI\Models\AIToolRegistryEntry;
use App\AI\ToolRegistry;
use App\AI\WriteActionRunner;
use Database\Seeders\AIToolRegistrySeeder;
use stdClass;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5625 — couverture outils AI : tout outil déclaré (registre
 * `ai_tool_registry` pour les read, config `ai.write_tools` pour les write)
 * DOIT avoir un handler effectif. Un outil « enregistré mais non implémenté »
 * faisait promettre l'action par le LLM puis échouer silencieusement.
 */
class ToolRegistryCoverageTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_every_registered_read_tool_has_an_intent_handler(): void
    {
        $registered = collect(app(ToolRegistry::class)->getToolsForRole('super_admin', 'coverage'))
            ->pluck('name')
            ->all();
        $writeTools = config('ai.write_tools', []);
        $registeredReadTools = array_values(array_diff($registered, $writeTools));

        $this->assertNotEmpty($registeredReadTools, 'le registre doit exposer des read-tools');

        $supported = app(IntentEngine::class)->supportedReadTools();
        foreach ($registeredReadTools as $tool) {
            $this->assertContains(
                $tool,
                $supported,
                "Read-tool '{$tool}' enregistré dans ai_tool_registry mais sans handler dans IntentEngine"
            );
        }
    }

    public function test_every_configured_write_tool_has_a_handler_and_is_registered(): void
    {
        $runner = app(WriteActionRunner::class);
        $registry = app(ToolRegistry::class);
        $configured = config('ai.write_tools', []);

        $this->assertNotEmpty($configured);
        foreach ($configured as $tool) {
            $this->assertContains(
                $tool,
                $runner->supportedWriteTools(),
                "Write-tool '{$tool}' dans config ai.write_tools mais sans handler dans WriteActionRunner"
            );
            $this->assertNotNull(
                $registry->findTool($tool),
                "Write-tool '{$tool}' configuré mais jamais exposé dans ai_tool_registry (promesse fantôme)"
            );
        }
    }

    public function test_every_supported_write_tool_is_declared_in_config(): void
    {
        $runner = app(WriteActionRunner::class);
        $configured = config('ai.write_tools', []);

        foreach ($runner->supportedWriteTools() as $tool) {
            $this->assertContains(
                $tool,
                $configured,
                "Write-tool '{$tool}' implémenté mais absent de config ai.write_tools"
            );
        }
    }

    public function test_no_registry_entry_is_active_but_missing_a_handler(): void
    {
        $writeTools = config('ai.write_tools', []);
        $engine = app(IntentEngine::class);
        $runner = app(WriteActionRunner::class);

        $active = AIToolRegistryEntry::query()->where('active', true)->pluck('name')->all();
        $readSupported = $engine->supportedReadTools();
        $writeSupported = $runner->supportedWriteTools();

        foreach ($active as $tool) {
            $isRead = in_array($tool, $readSupported, true);
            $isWrite = in_array($tool, $writeSupported, true);
            $isConfiguredWrite = in_array($tool, $writeTools, true);

            $this->assertTrue(
                $isRead || $isWrite,
                "Outil actif '{$tool}' dans ai_tool_registry sans handler (ni IntentEngine ni WriteActionRunner)"
            );
            if ($isWrite) {
                $this->assertTrue(
                    $isConfiguredWrite,
                    "Outil '{$tool}' a un handler write mais n'est pas déclaré dans config ai.write_tools"
                );
            }
        }
    }

    public function test_unknown_tool_returns_graceful_not_implemented_message(): void
    {
        $engine = app(IntentEngine::class);
        $handler = new \ReflectionMethod(IntentEngine::class, 'dispatchToolAction');

        $result = $handler->invoke($engine, 'get_unknown_tool', [], 'company-x', 1);

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('not yet implemented', $result['message']);
    }
}
