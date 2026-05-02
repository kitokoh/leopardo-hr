<?php

namespace App\Console\Commands;

use App\Contracts\FeatureRegistryInterface;
use App\Models\Feature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Commande de démonstration du Feature Registry
 *
 * Cette commande illustre l'utilisation complète du système de registre
 * des fonctionnalités avec des exemples concrets.
 */
class DemoFeatureRegistryCommand extends Command
{
    /**
     * Nom et signature de la commande
     *
     * @var string
     */
    protected $signature = 'features:demo
                            {--reset : Réinitialiser les données de démonstration}
                            {--mobile-version=1.0.0 : Version mobile pour les tests}';

    /**
     * Description de la commande
     *
     * @var string
     */
    protected $description = 'Démonstration complète du système Feature Registry';

    /**
     * Exécute la commande de démonstration
     */
    public function handle(FeatureRegistryInterface $registry): int
    {
        $this->info('🚀 Démonstration du Feature Registry');
        $this->newLine();

        if ($this->option('reset')) {
            $this->resetDemoData();
        }

        // 1. Créer des fonctionnalités d'exemple
        $this->info('📝 1. Création de fonctionnalités d\'exemple...');
        $this->createDemoFeatures($registry);

        // 2. Afficher les statistiques
        $this->info('📊 2. Statistiques du registre:');
        $this->displayStatistics($registry);

        // 3. Tester la récupération de fonctionnalités
        $this->info('🔍 3. Test de récupération des fonctionnalités:');
        $this->testFeatureRetrieval($registry);

        // 4. Tester la compatibilité mobile
        $this->info('📱 4. Test de compatibilité mobile:');
        $this->testMobileCompatibility($registry);

        // 5. Générer et afficher le manifeste
        $this->info('📋 5. Génération du manifeste:');
        $this->generateAndDisplayManifest($registry);

        // 6. Tester la synchronisation
        $this->info('🔄 6. Test de synchronisation:');
        $this->testSynchronization($registry);

        // 7. Tester le cache
        $this->info('💾 7. Test du système de cache:');
        $this->testCaching($registry);

        $this->newLine();
        $this->info('✅ Démonstration terminée avec succès!');

        return Command::SUCCESS;
    }

    /**
     * Réinitialise les données de démonstration
     */
    private function resetDemoData(): void
    {
        $this->warn('🗑️  Suppression des données de démonstration...');

        DB::table('features')->where('key', 'like', 'demo_%')->delete();

        $this->info('✅ Données supprimées.');
        $this->newLine();
    }

    /**
     * Crée des fonctionnalités d'exemple
     */
    private function createDemoFeatures(FeatureRegistryInterface $registry): void
    {
        $demoFeatures = [
            [
                'key' => 'demo_employee_management',
                'title' => 'Gestion des Employés (Démo)',
                'description' => 'Créer, modifier et gérer les employés de l\'entreprise',
                'endpoint' => '/api/v1/employees',
                'http_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                'parameters' => [
                    'list' => [
                        'page' => ['type' => 'integer', 'required' => false],
                        'search' => ['type' => 'string', 'required' => false],
                        'department' => ['type' => 'string', 'required' => false],
                    ],
                    'create' => [
                        'first_name' => ['type' => 'string', 'required' => true],
                        'last_name' => ['type' => 'string', 'required' => true],
                        'email' => ['type' => 'email', 'required' => true],
                        'department_id' => ['type' => 'integer', 'required' => true],
                    ],
                ],
                'response_schema' => [
                    'employee' => [
                        'id' => 'integer',
                        'first_name' => 'string',
                        'last_name' => 'string',
                        'email' => 'string',
                        'department' => 'object',
                    ],
                ],
                'permissions' => ['employees.view', 'employees.create', 'employees.update'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => null,
                'api_version' => 'v1',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'list',
                    'form_schema' => [
                        'fields' => [
                            ['name' => 'first_name', 'type' => 'text', 'label' => 'Prénom', 'required' => true],
                            ['name' => 'last_name', 'type' => 'text', 'label' => 'Nom', 'required' => true],
                            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'demo_attendance_tracking',
                'title' => 'Suivi des Présences (Démo)',
                'description' => 'Enregistrer et consulter les heures de présence',
                'endpoint' => '/api/v1/attendance',
                'http_methods' => ['GET', 'POST'],
                'parameters' => [
                    'checkin' => [
                        'location' => ['type' => 'object', 'required' => false],
                        'note' => ['type' => 'string', 'required' => false],
                    ],
                ],
                'response_schema' => [
                    'attendance' => [
                        'id' => 'integer',
                        'employee_id' => 'integer',
                        'check_in' => 'datetime',
                        'check_out' => 'datetime',
                        'location' => 'object',
                    ],
                ],
                'permissions' => ['attendance.view', 'attendance.create'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => null,
                'api_version' => 'v1',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'form',
                    'mobile_compatible' => true,
                ],
            ],
            [
                'key' => 'demo_advanced_reporting',
                'title' => 'Rapports Avancés (Démo)',
                'description' => 'Génération de rapports détaillés et analytics',
                'endpoint' => '/api/v1/reports/advanced',
                'http_methods' => ['GET'],
                'parameters' => [
                    'generate' => [
                        'type' => ['type' => 'enum', 'values' => ['monthly', 'quarterly', 'yearly']],
                        'format' => ['type' => 'enum', 'values' => ['pdf', 'excel', 'json']],
                    ],
                ],
                'response_schema' => [
                    'report' => [
                        'id' => 'string',
                        'type' => 'string',
                        'data' => 'object',
                        'generated_at' => 'datetime',
                    ],
                ],
                'permissions' => ['reports.advanced'],
                'mobile_version_min' => '1.5.0', // Version plus récente requise
                'mobile_version_max' => null,
                'api_version' => 'v1',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'dashboard',
                    'mobile_compatible' => true,
                ],
            ],
            [
                'key' => 'demo_legacy_feature',
                'title' => 'Fonctionnalité Héritée (Démo)',
                'description' => 'Ancienne fonctionnalité en cours de dépréciation',
                'endpoint' => '/api/v1/legacy/old-feature',
                'http_methods' => ['GET'],
                'parameters' => [],
                'response_schema' => ['data' => 'object'],
                'permissions' => ['legacy.access'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => '1.2.0', // Limitée aux anciennes versions
                'api_version' => 'v1',
                'status' => 'deprecated',
                'metadata' => [
                    'ui_type' => 'generic',
                    'deprecation_notice' => 'Cette fonctionnalité sera supprimée dans la version 2.0',
                ],
            ],
        ];

        foreach ($demoFeatures as $featureData) {
            $feature = new Feature($featureData);
            $registry->registerFeature($feature);

            $this->line("  ✅ {$feature->title} enregistrée");
        }

        $this->newLine();
    }

    /**
     * Affiche les statistiques du registre
     */
    private function displayStatistics(FeatureRegistryInterface $registry): void
    {
        $stats = $registry->getStatistics();

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total des fonctionnalités', $stats['total_features']],
                ['Fonctionnalités actives', $stats['active_features']],
                ['Fonctionnalités inactives', $stats['inactive_features']],
                ['Mises à jour récentes', $stats['recently_updated']],
            ]
        );

        if (! empty($stats['by_status'])) {
            $this->info('Par statut:');
            foreach ($stats['by_status'] as $status => $count) {
                $this->line("  - {$status}: {$count}");
            }
        }

        $this->newLine();
    }

    /**
     * Teste la récupération des fonctionnalités
     */
    private function testFeatureRetrieval(FeatureRegistryInterface $registry): void
    {
        // Test récupération de toutes les fonctionnalités
        $allFeatures = $registry->getFeatures();
        $this->line("  📋 Total des fonctionnalités: {$allFeatures->count()}");

        // Test récupération d'une fonctionnalité spécifique
        $specificFeature = $registry->getFeature('demo_employee_management');
        if ($specificFeature) {
            $this->line("  🎯 Fonctionnalité trouvée: {$specificFeature->title}");
        }

        // Test vérification d'existence
        $exists = $registry->hasFeature('demo_employee_management');
        $this->line('  ✅ Fonctionnalité existe: '.($exists ? 'Oui' : 'Non'));

        $this->newLine();
    }

    /**
     * Teste la compatibilité mobile
     */
    private function testMobileCompatibility(FeatureRegistryInterface $registry): void
    {
        $mobileVersion = $this->option('mobile-version');

        $compatibleFeatures = $registry->getCompatibleFeatures($mobileVersion);
        $this->line("  📱 Fonctionnalités compatibles avec v{$mobileVersion}: {$compatibleFeatures->count()}");

        foreach ($compatibleFeatures as $feature) {
            $maxVersion = $feature->mobile_version_max ? " - {$feature->mobile_version_max}" : '';
            $this->line("    - {$feature->title} (v{$feature->mobile_version_min}{$maxVersion})");
        }

        // Test avec une version plus ancienne
        $oldCompatible = $registry->getCompatibleFeatures('1.0.0');
        $this->line("  📱 Fonctionnalités compatibles avec v1.0.0: {$oldCompatible->count()}");

        $this->newLine();
    }

    /**
     * Génère et affiche le manifeste
     */
    private function generateAndDisplayManifest(FeatureRegistryInterface $registry): void
    {
        $mobileVersion = $this->option('mobile-version');
        $manifest = $registry->getManifest($mobileVersion);

        $this->line("  📋 Manifeste généré pour la version mobile {$mobileVersion}:");
        $this->line("    - Version API: {$manifest['version']}");
        $this->line("    - Généré le: {$manifest['generated_at']}");
        $this->line("    - Nombre de fonctionnalités: {$manifest['total_features']}");

        if ($this->option('verbose')) {
            $this->newLine();
            $this->info('Détail des fonctionnalités:');

            $headers = ['Clé', 'Titre', 'Endpoint', 'Méthodes', 'Permissions'];
            $rows = [];

            foreach ($manifest['features'] as $feature) {
                $rows[] = [
                    $feature['key'],
                    $feature['title'],
                    $feature['endpoint'],
                    implode(', ', $feature['methods']),
                    implode(', ', $feature['permissions']),
                ];
            }

            $this->table($headers, $rows);
        }

        $this->newLine();
    }

    /**
     * Teste la synchronisation
     */
    private function testSynchronization(FeatureRegistryInterface $registry): void
    {
        $this->line('  🔄 Lancement de la synchronisation...');

        $result = $registry->synchronize();

        $this->line("    - Nouvelles fonctionnalités: {$result['new']}");
        $this->line("    - Fonctionnalités mises à jour: {$result['updated']}");
        $this->line("    - Fonctionnalités supprimées: {$result['removed']}");

        if (! empty($result['errors'])) {
            $this->warn('    - Erreurs: '.count($result['errors']));
            foreach ($result['errors'] as $error) {
                $this->line("      • {$error}");
            }
        } else {
            $this->line('    ✅ Aucune erreur');
        }

        $this->newLine();
    }

    /**
     * Teste le système de cache
     */
    private function testCaching(FeatureRegistryInterface $registry): void
    {
        $this->line('  💾 Test du cache...');

        // Premier appel (devrait mettre en cache)
        $start = microtime(true);
        $features1 = $registry->getFeatures();
        $time1 = round((microtime(true) - $start) * 1000, 2);

        // Deuxième appel (devrait utiliser le cache)
        $start = microtime(true);
        $features2 = $registry->getFeatures();
        $time2 = round((microtime(true) - $start) * 1000, 2);

        $this->line("    - Premier appel: {$time1}ms ({$features1->count()} fonctionnalités)");
        $this->line("    - Deuxième appel: {$time2}ms ({$features2->count()} fonctionnalités)");

        if ($time2 < $time1) {
            $this->line('    ✅ Cache fonctionnel (amélioration: '.round(($time1 - $time2) / $time1 * 100, 1).'%)');
        }

        // Test invalidation du cache
        $registry->invalidateCache();
        $this->line('    🗑️  Cache invalidé');

        $this->newLine();
    }
}
