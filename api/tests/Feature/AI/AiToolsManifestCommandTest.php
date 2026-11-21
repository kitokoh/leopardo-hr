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
        $this->artisan('ai:tools:manifest')
            ->assertSuccessful();

        $definitions = AIToolDefinitionRegistry::all();
        $this->assertNotEmpty($definitions, 'les providers doivent enregistrer leurs définitions au boot');

        $output = $this->artisan('ai:tools:manifest');
        $output->assertSuccessful();
    }

    public function test_manifest_written_to_file_matches_registry(): void
    {
        $path = $this->tmpDir.'/tools.json';

        $this->artisan('ai:tools:manifest', ['--output' => $path])->assertSuccessful();

        $this->assertFileExists($path);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $decoded['schema_version']);
        $this->assertIsArray($decoded['tools']);

        $names = array_column($decoded['tools'], 'name');
        $this->assertSame(
            array_keys(AIToolDefinitionRegistry::all()),
            array_values(array_intersect($names, array_keys(AIToolDefinitionRegistry::all()))),
            'le manifeste doit contenir exactement les outils du registre'
        );

        foreach ($decoded['tools'] as $tool) {
            $this->assertArrayHasKey('inputSchema', $tool);
            $this->assertArrayHasKey('sensitivity', $tool);
            $this->assertContains($tool['sensitivity'], ['read', 'write', 'send']);
            $this->assertNotSame('', (string) $tool['permission']);
        }
    }

    public function test_check_fails_when_manifest_is_missing(): void
    {
        // Le manifeste versionné n'existe pas dans l'arbre de test isolé :
        // on force un chemin absent en déplaçant temporairement le fichier réel.
        $real = base_path('..'.DIRECTORY_SEPARATOR.AiToolsManifestCommand::DEFAULT_OUTPUT);
        $backup = $real.'.bak-test';

        $existed = File::exists($real);
        if ($existed) {
            File::move($real, $backup);
        }

        try {
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
            $this->artisan('ai:tools:manifest', ['--output' => $real])->assertSuccessful();
            $this->artisan('ai:tools:manifest', ['--check' => true])->assertSuccessful();
        } finally {
            File::delete($real);
            if ($existed) {
                File::move($backup, $real);
            }
        }
    }
}
