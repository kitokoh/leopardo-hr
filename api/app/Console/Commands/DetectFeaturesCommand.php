<?php

namespace App\Console\Commands;

use App\Contracts\FeatureDetectorInterface;
use Illuminate\Console\Command;

/**
 * Commande Artisan pour détecter les nouvelles fonctionnalités API
 */
class DetectFeaturesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'features:detect 
                            {--dry-run : Afficher les fonctionnalités détectées sans les enregistrer}
                            {--details : Afficher des informations détaillées}';

    /**
     * The console command description.
     */
    protected $description = 'Détecte automatiquement les nouvelles fonctionnalités API';

    /**
     * Execute the console command.
     */
    public function handle(FeatureDetectorInterface $detector): int
    {
        $this->info('🔍 Détection des nouvelles fonctionnalités API...');

        try {
            // Scanner les routes
            $this->info('📡 Scan des routes API...');
            $routes = $detector->scanRoutes();
            $this->info("✅ {$routes->count()} routes API trouvées");

            if ($this->option('details')) {
                $this->table(
                    ['URI', 'Méthodes', 'Contrôleur', 'Action'],
                    $routes->map(fn ($route) => [
                        $route['uri'],
                        implode(', ', $route['methods']),
                        class_basename($route['controller_class']),
                        $route['method'],
                    ])->toArray()
                );
            }

            // Détecter les nouvelles fonctionnalités
            $this->info('🆕 Détection des nouvelles fonctionnalités...');
            $newFeatures = $detector->detectNewFeatures();

            if ($newFeatures->isEmpty()) {
                $this->info('✨ Aucune nouvelle fonctionnalité détectée');

                return self::SUCCESS;
            }

            $this->info("🎉 {$newFeatures->count()} nouvelles fonctionnalités détectées !");

            // Afficher les fonctionnalités détectées
            $this->table(
                ['Clé', 'Titre', 'Endpoint', 'Méthodes', 'Type UI'],
                $newFeatures->map(fn ($feature) => [
                    $feature['key'],
                    $feature['title'],
                    $feature['endpoint'],
                    implode(', ', $feature['http_methods']),
                    $feature['metadata']['ui_type'] ?? 'generic',
                ])->toArray()
            );

            if ($this->option('dry-run')) {
                $this->warn('🔍 Mode dry-run : aucune fonctionnalité n\'a été enregistrée');

                return self::SUCCESS;
            }

            // Enregistrer les fonctionnalités (sera implémenté dans la phase suivante)
            $this->warn('💾 Enregistrement des fonctionnalités (à implémenter dans le Feature Registry)');

            // Détecter les changements
            $this->info('🔄 Détection des changements...');
            $changes = $detector->detectChanges();

            if ($changes->isNotEmpty()) {
                $this->info("⚠️  {$changes->count()} changements détectés dans les fonctionnalités existantes");

                foreach ($changes as $change) {
                    $icon = $change['type'] === 'removed' ? '🗑️' : '📝';
                    $this->line("{$icon} {$change['type']}: {$change['feature_key']}");
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la détection : {$e->getMessage()}");

            if ($this->option('details')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
