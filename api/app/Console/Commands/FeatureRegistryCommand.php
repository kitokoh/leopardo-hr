<?php

namespace App\Console\Commands;

use App\Contracts\FeatureRegistryInterface;
use App\Models\Feature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Commande Artisan pour gerer le registre des fonctionnalites API.
 */
class FeatureRegistryCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'features:registry
                            {action : Action a effectuer (sync, list, stats, clear-cache)}
                            {--api-version= : Version API specifique}
                            {--mobile-version= : Version mobile pour la compatibilite}
                            {--format=table : Format de sortie (table, json)}';

    /**
     * @var string
     */
    protected $description = 'Gere le registre des fonctionnalites API';

    public function handle(FeatureRegistryInterface $registry): int
    {
        $action = $this->argumentString('action');

        try {
            return match ($action) {
                'sync' => $this->handleSync($registry),
                'list' => $this->handleList($registry),
                'stats' => $this->handleStats($registry),
                'clear-cache' => $this->handleClearCache($registry),
                default => $this->handleUnknownAction($action),
            };
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'execution: {$e->getMessage()}");
            Log::error('Feature registry command failed', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    private function handleSync(FeatureRegistryInterface $registry): int
    {
        $this->info('Synchronisation du registre des fonctionnalites...');

        $result = $registry->synchronize();

        $this->info('Synchronisation terminee:');
        $this->line('  - Nouvelles fonctionnalites: '.$result['new']);
        $this->line('  - Fonctionnalites mises a jour: '.$result['updated']);
        $this->line('  - Fonctionnalites supprimees: '.$result['removed']);

        if ($result['errors'] !== []) {
            $this->warn('Erreurs rencontrees:');
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        return Command::SUCCESS;
    }

    private function handleList(FeatureRegistryInterface $registry): int
    {
        $version = $this->optionString('api-version');
        $mobileVersion = $this->optionString('mobile-version');
        $format = $this->optionString('format', 'table');

        if ($mobileVersion !== null) {
            $features = $registry->getCompatibleFeatures($mobileVersion);
            $this->info("Fonctionnalites compatibles avec la version mobile {$mobileVersion}:");
        } else {
            $features = $registry->getFeatures($version);
            $title = $version !== null ? "Fonctionnalites pour l'API {$version}:" : 'Toutes les fonctionnalites:';
            $this->info($title);
        }

        if ($features->isEmpty()) {
            $this->warn('Aucune fonctionnalite trouvee.');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line($this->jsonLine($features->toArray()));

            return Command::SUCCESS;
        }

        $headers = ['Cle', 'Titre', 'Endpoint', 'Methodes', 'Version API', 'Statut'];
        /** @var array<int, array<int, string|null>> $rows */
        $rows = $features->map(fn (Feature $feature): array => $this->featureRow($feature))->toArray();

        $this->table($headers, $rows);

        return Command::SUCCESS;
    }

    private function handleStats(FeatureRegistryInterface $registry): int
    {
        $stats = $registry->getStatistics();
        $format = $this->optionString('format', 'table');

        if ($format === 'json') {
            $this->line($this->jsonLine($stats));

            return Command::SUCCESS;
        }

        $this->info('Statistiques du registre des fonctionnalites:');
        $this->line('  Total des fonctionnalites: '.$this->statInt($stats, 'total_features'));
        $this->line('  Fonctionnalites actives: '.$this->statInt($stats, 'active_features'));
        $this->line('  Fonctionnalites inactives: '.$this->statInt($stats, 'inactive_features'));
        $this->line('  Mises a jour recentes (7 jours): '.$this->statInt($stats, 'recently_updated'));

        $byApiVersion = $this->statArray($stats, 'by_api_version');
        if ($byApiVersion !== []) {
            $this->line("\nPar version API:");
            foreach ($byApiVersion as $version => $count) {
                $this->line('  - '.(string) $version.': '.(string) $count);
            }
        }

        $byStatus = $this->statArray($stats, 'by_status');
        if ($byStatus !== []) {
            $this->line("\nPar statut:");
            foreach ($byStatus as $status => $count) {
                $this->line('  - '.(string) $status.': '.(string) $count);
            }
        }

        $cacheStatus = $this->statArray($stats, 'cache_status');
        $this->line("\nCache:");
        $this->line('  - Driver: '.(string) ($cacheStatus['cache_driver'] ?? 'unknown'));
        $this->line('  - Manifeste en cache: '.$this->boolLabel($cacheStatus['manifest_cached'] ?? false));
        $this->line('  - Fonctionnalites en cache: '.$this->boolLabel($cacheStatus['features_cached'] ?? false));

        $lastSynchronization = $stats['last_synchronization'] ?? null;
        if (is_scalar($lastSynchronization) && (string) $lastSynchronization !== '') {
            $this->line("\nDerniere synchronisation: {$lastSynchronization}");
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string|null>
     */
    private function featureRow(Feature $feature): array
    {
        return [
            $feature->key,
            $feature->title,
            $feature->endpoint,
            implode(', ', array_map('strval', $feature->http_methods ?? [])),
            $feature->api_version,
            $feature->status,
        ];
    }

    private function handleClearCache(FeatureRegistryInterface $registry): int
    {
        $this->info('Suppression du cache du registre...');

        $registry->invalidateCache();

        $this->info('Cache supprime avec succes.');

        return Command::SUCCESS;
    }

    private function handleUnknownAction(string $action): int
    {
        $this->error("Action inconnue: {$action}");
        $this->info('Actions disponibles: sync, list, stats, clear-cache');

        return Command::FAILURE;
    }

    private function argumentString(string $key): string
    {
        $value = $this->argument($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function optionString(string $key, ?string $default = null): ?string
    {
        $value = $this->option($key);

        if ($value === null || $value === false || is_array($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function jsonLine(array $value): string
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT);

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function statInt(array $stats, string $key): int
    {
        $value = $stats[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string|int, mixed>
     */
    private function statArray(array $stats, string $key): array
    {
        $value = $stats[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    private function boolLabel(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Oui' : 'Non';
    }
}
