<?php

namespace App\Console\Commands;

use App\Contracts\FeatureRegistryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Commande Artisan pour gérer le registre des fonctionnalités
 *
 * Fournit des commandes pour synchroniser, afficher et gérer
 * le registre des fonctionnalités API.
 */
class FeatureRegistryCommand extends Command
{
    /**
     * Nom et signature de la commande
     *
     * @var string
     */
    protected $signature = 'features:registry
                            {action : Action à effectuer (sync, list, stats, clear-cache)}
                            {--version= : Version API spécifique}
                            {--mobile-version= : Version mobile pour la compatibilité}
                            {--format=table : Format de sortie (table, json)}';

    /**
     * Description de la commande
     *
     * @var string
     */
    protected $description = 'Gère le registre des fonctionnalités API';

    /**
     * Exécute la commande
     *
     * @param FeatureRegistryInterface $registry
     * @return int
     */
    public function handle(FeatureRegistryInterface $registry): int
    {
        $action = $this->argument('action');

        try {
            switch ($action) {
                case 'sync':
                    return $this->handleSync($registry);

                case 'list':
                    return $this->handleList($registry);

                case 'stats':
                    return $this->handleStats($registry);

                case 'clear-cache':
                    return $this->handleClearCache($registry);

                default:
                    $this->error("Action inconnue: {$action}");
                    $this->info("Actions disponibles: sync, list, stats, clear-cache");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'exécution: {$e->getMessage()}");
            Log::error('Feature registry command failed', [
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Gère la synchronisation du registre
     *
     * @param FeatureRegistryInterface $registry
     * @return int
     */
    private function handleSync(FeatureRegistryInterface $registry): int
    {
        $this->info('Synchronisation du registre des fonctionnalités...');

        $result = $registry->synchronize();

        $this->info("Synchronisation terminée:");
        $this->line("  - Nouvelles fonctionnalités: {$result['new']}");
        $this->line("  - Fonctionnalités mises à jour: {$result['updated']}");
        $this->line("  - Fonctionnalités supprimées: {$result['removed']}");

        if (!empty($result['errors'])) {
            $this->warn("Erreurs rencontrées:");
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Gère l'affichage de la liste des fonctionnalités
     *
     * @param FeatureRegistryInterface $registry
     * @return int
     */
    private function handleList(FeatureRegistryInterface $registry): int
    {
        $version = $this->option('version');
        $mobileVersion = $this->option('mobile-version');
        $format = $this->option('format');

        if ($mobileVersion) {
            $features = $registry->getCompatibleFeatures($mobileVersion);
            $this->info("Fonctionnalités compatibles avec la version mobile {$mobileVersion}:");
        } else {
            $features = $registry->getFeatures($version);
            $title = $version ? "Fonctionnalités pour l'API {$version}:" : "Toutes les fonctionnalités:";
            $this->info($title);
        }

        if ($features->isEmpty()) {
            $this->warn('Aucune fonctionnalité trouvée.');
            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($features->toArray(), JSON_PRETTY_PRINT));
        } else {
            $headers = ['Clé', 'Titre', 'Endpoint', 'Méthodes', 'Version API', 'Statut'];
            $rows = $features->map(function ($feature) {
                return [
                    $feature->key,
                    $feature->title,
                    $feature->endpoint,
                    implode(', ', $feature->http_methods),
                    $feature->api_version,
                    $feature->status,
                ];
            })->toArray();

            $this->table($headers, $rows);
        }

        return Command::SUCCESS;
    }

    /**
     * Gère l'affichage des statistiques
     *
     * @param FeatureRegistryInterface $registry
     * @return int
     */
    private function handleStats(FeatureRegistryInterface $registry): int
    {
        $stats = $registry->getStatistics();
        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
        } else {
            $this->info('Statistiques du registre des fonctionnalités:');
            $this->line("  Total des fonctionnalités: {$stats['total_features']}");
            $this->line("  Fonctionnalités actives: {$stats['active_features']}");
            $this->line("  Fonctionnalités inactives: {$stats['inactive_features']}");
            $this->line("  Mises à jour récentes (7 jours): {$stats['recently_updated']}");

            if (!empty($stats['by_api_version'])) {
                $this->line("\nPar version API:");
                foreach ($stats['by_api_version'] as $version => $count) {
                    $this->line("  - {$version}: {$count}");
                }
            }

            if (!empty($stats['by_status'])) {
                $this->line("\nPar statut:");
                foreach ($stats['by_status'] as $status => $count) {
                    $this->line("  - {$status}: {$count}");
                }
            }

            $this->line("\nCache:");
            $cacheStatus = $stats['cache_status'];
            $this->line("  - Driver: {$cacheStatus['cache_driver']}");
            $this->line("  - Manifeste en cache: " . ($cacheStatus['manifest_cached'] ? 'Oui' : 'Non'));
            $this->line("  - Fonctionnalités en cache: " . ($cacheStatus['features_cached'] ? 'Oui' : 'Non'));

            if ($stats['last_synchronization']) {
                $this->line("\nDernière synchronisation: {$stats['last_synchronization']}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Gère la suppression du cache
     *
     * @param FeatureRegistryInterface $registry
     * @return int
     */
    private function handleClearCache(FeatureRegistryInterface $registry): int
    {
        $this->info('Suppression du cache du registre...');

        $registry->invalidateCache();

        $this->info('Cache supprimé avec succès.');

        return Command::SUCCESS;
    }
}