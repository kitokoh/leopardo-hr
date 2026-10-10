<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ApiEndpointRegistryInterface;
use App\Modules\Billing\Domain\Models\Feature;
use Illuminate\Console\Command;

/**
 * BOS-015 (#8202) : commande console d'inventaire API endpoints (manifeste mobile).
 */
class FeatureRegistryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'features:registry 
                            {action : Action à exécuter (sync, list, stats, clear-cache)}
                            {--version= : Version de l\'API ou de l\'application mobile}
                            {--format=table : Format de sortie (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gère le registre des fonctionnalités API pour les applications mobiles';

    public function handle(ApiEndpointRegistryInterface $registry): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'sync' => $this->handleSync($registry),
            'list' => $this->handleList($registry),
            'stats' => $this->handleStats($registry),
            'clear-cache' => $this->handleClearCache($registry),
            default => $this->handleUnknownAction((string) $action),
        };
    }

    private function handleSync(ApiEndpointRegistryInterface $registry): int
    {
        $this->info('Démarrage de la synchronisation du registre des fonctionnalités...');

        try {
            $result = $registry->synchronize();

            $this->info('Synchronisation terminée avec succès :');
            $this->table(
                ['Type', 'Nombre'],
                [
                    ['Nouvelles fonctionnalités', $result['new']],
                    ['Fonctionnalités mises à jour', $result['updated']],
                    ['Fonctionnalités supprimées', $result['removed']],
                    ['Erreurs', count($result['errors'])],
                ]
            );

            if (! empty($result['errors'])) {
                $this->warn('Erreurs rencontrées lors de la synchronisation :');
                foreach ($result['errors'] as $error) {
                    $this->error("- {$error}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Erreur lors de la synchronisation : {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function handleList(ApiEndpointRegistryInterface $registry): int
    {
        $version = $this->option('version');
        $format = $this->option('format');

        $features = $version
            ? $registry->getFeatures((string) $version)
            : $registry->getFeatures();

        if ($features->isEmpty()) {
            $this->warn('Aucune fonctionnalité trouvée.');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($features->map(fn (Feature $f) => $f->toManifestArray()), JSON_PRETTY_PRINT) ?: '');

            return Command::SUCCESS;
        }

        $headers = ['Clé', 'Titre', 'Endpoint', 'Version API', 'Version Mobile Min', 'Statut'];
        $rows = $features->map(fn (Feature $feature) => [
            $feature->key,
            $feature->title,
            $feature->endpoint,
            $feature->api_version,
            $feature->mobile_version_min,
            $feature->status,
        ])->toArray();

        $this->table($headers, $rows);
        $this->info("Total : {$features->count()} fonctionnalité(s)");

        return Command::SUCCESS;
    }

    private function handleStats(ApiEndpointRegistryInterface $registry): int
    {
        $stats = $registry->getStatistics();
        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT) ?: '');

            return Command::SUCCESS;
        }

        $this->info('Statistiques du Registre des Fonctionnalités :');

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total des fonctionnalités', $stats['total_features']],
                ['Fonctionnalités actives', $stats['active_features']],
                ['Fonctionnalités inactives', $stats['inactive_features']],
                ['Modifiées récemment (7j)', $stats['recently_updated']],
                ['Dernière synchronisation', $stats['last_synchronization'] ?? 'Jamais'],
            ]
        );

        if (! empty($stats['by_api_version'])) {
            $this->info("\nRépartition par version d'API :");
            $this->table(
                ['Version API', 'Nombre'],
                collect($stats['by_api_version'])->map(fn ($count, $version) => [$version, $count])->toArray()
            );
        }

        if (! empty($stats['by_status'])) {
            $this->info("\nRépartition par statut :");
            $this->table(
                ['Statut', 'Nombre'],
                collect($stats['by_status'])->map(fn ($count, $status) => [$status, $count])->toArray()
            );
        }

        $this->info("\nÉtat du cache :");
        $this->table(
            ['Composant', 'En cache'],
            [
                ['Manifeste', $stats['cache_status']['manifest_cached'] ? 'Oui' : 'Non'],
                ['Fonctionnalités', $stats['cache_status']['features_cached'] ? 'Oui' : 'Non'],
                ['Driver de cache', $stats['cache_status']['cache_driver']],
            ]
        );

        return Command::SUCCESS;
    }

    private function handleClearCache(ApiEndpointRegistryInterface $registry): int
    {
        $this->info('Vidage du cache du registre des fonctionnalités...');

        try {
            $registry->invalidateCache();
            $this->info('Cache vidé avec succès.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Erreur lors du vidage du cache : {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function handleUnknownAction(string $action): int
    {
        $this->error("Action inconnue : '{$action}'");
        $this->info('Actions disponibles : sync, list, stats, clear-cache');

        return Command::INVALID;
    }
}
