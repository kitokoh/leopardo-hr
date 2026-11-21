<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolDefinitionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * ai:tools:manifest — exporte le catalogue d'outils déclaré par les BC
 * (contrat A3, `AIToolDefinitionRegistry`) en manifeste JSON versionnable
 * (MCP-1, issue #9735, EPIC #9739).
 *
 * Le manifeste est la SOURCE UNIQUE DE VÉRITÉ du serveur MCP externe
 * (`dev-hub/mcp-server`, MCP-2 #9736) : aucun nom d'outil n'est codé en dur
 * côté serveur MCP, il découvre les outils via ce fichier — exactement comme
 * l'hôte BC-23 découvre les définitions via le registre (ADR-0019, option 3
 * « pont hybride »).
 *
 * Usage :
 *   php artisan ai:tools:manifest                       # JSON sur stdout
 *   php artisan ai:tools:manifest --output=chemin.json  # écrit le fichier
 *   php artisan ai:tools:manifest --check               # garde anti-drift CI
 *
 * `--check` échoue (exit 1) si le fichier versionné diverge du registre :
 * un outil ajouté/retiré/modifié sans régénérer le manifeste fait échouer la
 * CI (garde `dev-hub/tools/check-mcp-manifest-drift.sh`, MCP-4 #9738).
 */
class AiToolsManifestCommand extends Command
{
    /**
     * Chemin par défaut du manifeste versionné (relatif à la racine du repo).
     */
    public const DEFAULT_OUTPUT = 'dev-hub/mcp-server/manifest/tools.json';

    protected $signature = 'ai:tools:manifest
        {--output= : chemin du fichier à écrire (défaut : stdout)}
        {--check : vérifie que le fichier versionné est à jour (exit 1 sinon)}';

    protected $description = 'Exporte le catalogue d\'outils IA (AIToolDefinitionRegistry) en manifeste JSON pour le serveur MCP (MCP-1, #9735).';

    public function handle(): int
    {
        $violations = AIToolDefinitionRegistry::violations();
        if ($violations !== []) {
            foreach ($violations as $violation) {
                $this->error($violation);
            }

            return self::FAILURE;
        }

        $json = $this->render();

        if ((bool) $this->option('check')) {
            return $this->check($json);
        }

        /** @var mixed $output */
        $output = $this->option('output');
        if (is_string($output) && $output !== '') {
            $path = $this->absolutePath($output);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json);
            $this->info(sprintf('Manifeste écrit : %s (%d outils).', $output, count(AIToolDefinitionRegistry::all())));

            return self::SUCCESS;
        }

        $this->output->write($json);

        return self::SUCCESS;
    }

    /**
     * Sérialise le registre en JSON stable (clés triées, 2 espaces, newline
     * final) — un ordre déterministe est indispensable pour que `--check`
     * compare deux rendus identiques.
     */
    private function render(): string
    {
        $tools = [];
        foreach (AIToolDefinitionRegistry::all() as $definition) {
            $tools[] = $this->serialize($definition);
        }

        usort($tools, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        $manifest = [
            'schema_version' => 1,
            'generated_by' => 'php artisan ai:tools:manifest',
            'tools' => $tools,
        ];

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Sérialisation du manifeste impossible.');
        }

        return $encoded."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AIToolDefinition $definition): array
    {
        return [
            'name' => $definition->name,
            'description' => $definition->description,
            'inputSchema' => $definition->inputSchema === []
                ? ['type' => 'object', 'properties' => new \stdClass]
                : $definition->inputSchema,
            'outputSchema' => $definition->outputSchema,
            'permission' => $definition->permission,
            'sensitivity' => $definition->sensitivity->value,
            'bc' => $definition->bc,
            'version' => $definition->version,
        ];
    }

    private function check(string $expected): int
    {
        $path = $this->absolutePath(self::DEFAULT_OUTPUT);

        if (! File::exists($path)) {
            $this->error(sprintf('Manifeste absent : %s — régénérer avec `php artisan ai:tools:manifest --output=%s`.', self::DEFAULT_OUTPUT, self::DEFAULT_OUTPUT));

            return self::FAILURE;
        }

        $current = File::get($path);
        if ($current !== $expected) {
            $this->error(sprintf('Manifeste MCP désynchronisé : %s diverge du registre AIToolDefinitionRegistry (MCP-1 #9735).', self::DEFAULT_OUTPUT));
            $this->line('Régénérer : php artisan ai:tools:manifest --output='.self::DEFAULT_OUTPUT);

            return self::FAILURE;
        }

        $this->info(sprintf('Manifeste MCP à jour (%d outils).', count(AIToolDefinitionRegistry::all())));

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path('..'.DIRECTORY_SEPARATOR.$path);
    }
}
