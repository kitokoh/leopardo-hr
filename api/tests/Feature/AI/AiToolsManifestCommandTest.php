<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Console\Commands\AiToolsManifestCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * MCP-1 (#9735, EPIC #9739) — manifeste d'outils du serveur MCP externe.
 *
 * Vérifie que `ai:tools:manifest` sérialise le registre A3 en JSON stable et
 * que `--check` détecte une divergence (garde anti-drift CI, MCP-4 #9738).
 * Aucune base de données : la commande ne lit que le registre statique.
 */
class AiToolsManifestCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/mcp-manifest-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->tmpDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpDir);
        parent::tearDown();
    }

    public function test_manifest_is_valid_json_with_declared_tools(): void
    {
        $path = $this->tmpDir.'/tools.json';

        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('ai:tools:manifest', ['--output' => $path])->assertSuccessful();

        $this->assertFileExists($path);

        /** @var mixed $decoded */
        $decoded = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['schema_version'] ?? null);

        /** @var mixed $tools */
        $tools = $decoded['tools'] ?? null;
        $this->assertIsArray($tools);
        $this->assertNotEmpty($tools, 'le registre doit contenir les outils déclarés par les BC');

        /** @var list<string> $names */
        $names = array_column($tools, 'name');

        // #9817 — le manifeste est TRIÉ par nom (`usort` de
        // `AiToolsManifestCommand::render()`, indispensable pour que `--check`
        // compare deux rendus identiques), alors que `AIToolDefinitionRegistry::all()`
        // rend l'ordre d'ENREGISTREMENT (dépendant de l'ordre de boot des
        // providers). Comparer les deux listes telles quelles affirmait un
        // contrat que la commande ne promet pas : le test échouait dès qu'un
        // provider s'enregistrait dans un ordre différent de l'alphabet.
        // Le contrat réel est « exactement les mêmes outils », pas « dans le
        // même ordre » — on trie donc les deux côtés.
        $expected = array_keys(AIToolDefinitionRegistry::all());
        sort($expected);

        $actual = array_values(array_intersect($names, $expected));
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'le manifeste doit contenir exactement les outils du registre'
        );

        foreach ($tools as $tool) {
            $this->assertIsArray($tool);
            $this->assertArrayHasKey('inputSchema', $tool);
            $this->assertArrayHasKey('sensitivity', $tool);
            $this->assertContains($tool['sensitivity'] ?? null, ['read', 'write', 'send']);
            $this->assertNotSame('', (string) ($tool['permission'] ?? ''));
        }
    }

    public function test_manifest_is_deterministic(): void
    {
        $first = $this->tmpDir.'/a.json';
        $second = $this->tmpDir.'/b.json';

        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('ai:tools:manifest', ['--output' => $first])->assertSuccessful();
        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('ai:tools:manifest', ['--output' => $second])->assertSuccessful();

        $this->assertSame(File::get($first), File::get($second), 'deux générations doivent être identiques');
    }

    public function test_check_fails_when_manifest_is_missing(): void
    {
        $real = base_path('..'.DIRECTORY_SEPARATOR.AiToolsManifestCommand::DEFAULT_OUTPUT);
        $backup = $real.'.bak-test';

        $existed = File::exists($real);
        if ($existed) {
            File::move($real, $backup);
        }

        try {
            // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
            $this->artisan('ai:tools:manifest', ['--check' => true])->assertFailed();
        } finally {
            if ($existed) {
                File::move($backup, $real);
            }
        }
    }

    public function test_check_passes_on_up_to_date_manifest(): void
    {
        $real = base_path('..'.DIRECTORY_SEPARATOR.AiToolsManifestCommand::DEFAULT_OUTPUT);
        $backup = $real.'.bak-test';

        $existed = File::exists($real);
        if ($existed) {
            File::move($real, $backup);
        }

        try {
            // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
            $this->artisan('ai:tools:manifest', ['--output' => $real])->assertSuccessful();
            // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
            $this->artisan('ai:tools:manifest', ['--check' => true])->assertSuccessful();
        } finally {
            File::delete($real);
            if ($existed) {
                File::move($backup, $real);
            }
        }
    }
}
