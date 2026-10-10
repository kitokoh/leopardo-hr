<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ApiEndpointRegistryInterface;
use App\Modules\Billing\Domain\Models\Feature;
use Illuminate\Console\Command;

/**
 * BOS-015 (#8202) : commande console de démo pour ApiEndpointRegistry.
 */
class DemoFeatureRegistryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'features:demo';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Démontre les fonctionnalités du registre des endpoints API pour mobile';

    public function handle(ApiEndpointRegistryInterface $registry): int
    {
        $this->info('🚀 Démonstration du Registre des Fonctionnalités API (BOS-015)');
        $this->line('================================================================');

        $this->createDemoFeatures($registry);
        $this->displayStatistics($registry);
        $this->testFeatureRetrieval($registry);
        $this->testMobileCompatibility($registry);
        $this->generateAndDisplayManifest($registry);
        $this->testSynchronization($registry);
        $this->testCaching($registry);

        $this->line('================================================================');
        $this->info('✨ Démonstration terminée avec succès !');

        return Command::SUCCESS;
    }

    private function createDemoFeatures(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n1. 📝 Enregistrement de fonctionnalités de démonstration...");

        $features = [
            [
                'key' => 'auth.login',
                'title' => 'Authentification Utilisateur',
                'description' => 'Connexion des employés via email/mot de passe',
                'endpoint' => '/api/v1/auth/login',
                'http_methods' => ['POST'],
                'parameters' => [
                    'email' => ['type' => 'string', 'required' => true],
                    'password' => ['type' => 'string', 'required' => true],
                ],
                'response_schema' => [
                    'token' => ['type' => 'string'],
                    'user' => ['type' => 'object'],
                ],
                'permissions' => [],
                'mobile_version_min' => '1.0.0',
                'api_version' => 'v1',
                'status' => 'active',
            ],
            [
                'key' => 'attendance.punch',
                'title' => 'Pointage Mobile',
                'description' => 'Enregistrement des entrées/sorties avec géolocalisation',
                'endpoint' => '/api/v1/attendance/punch',
                'http_methods' => ['POST'],
                'parameters' => [
                    'type' => ['type' => 'string', 'required' => true, 'enum' => ['in', 'out']],
                    'latitude' => ['type' => 'number', 'required' => true],
                    'longitude' => ['type' => 'number', 'required' => true],
                ],
                'response_schema' => [
                    'punch_id' => ['type' => 'integer'],
                    'timestamp' => ['type' => 'string'],
                ],
                'permissions' => ['attendance.punch'],
                'mobile_version_min' => '1.2.0',
                'api_version' => 'v1',
                'status' => 'active',
            ],
        ];

        foreach ($features as $featureData) {
            $feature = new Feature($featureData);
            $registry->registerFeature($feature);
            $this->line("   ✅ Enregistré : {$feature->title} ({$feature->key})");
        }
    }

    private function displayStatistics(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n2. 📊 Statistiques du registre...");

        $stats = $registry->getStatistics();

        $this->line("   • Total des fonctionnalités : {$stats['total_features']}");
        $this->line("   • Fonctionnalités actives : {$stats['active_features']}");
    }

    private function testFeatureRetrieval(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n3. 🔍 Récupération de fonctionnalités...");

        $feature = $registry->getFeature('auth.login');
        if ($feature) {
            $this->line("   ✅ Trouvé par clé 'auth.login' : {$feature->title}");
        }

        $allFeatures = $registry->getFeatures();
        $this->line("   ✅ Récupéré {$allFeatures->count()} fonctionnalité(s) au total");
    }

    private function testMobileCompatibility(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n4. 📱 Test de compatibilité mobile...");

        $versions = ['1.0.0', '1.2.0', '2.0.0'];

        foreach ($versions as $version) {
            $compatible = $registry->getCompatibleFeatures($version);
            $this->line("   • Version mobile {$version} : {$compatible->count()} fonctionnalité(s) compatible(s)");
        }
    }

    private function generateAndDisplayManifest(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n5. 📋 Génération du manifeste mobile...");

        $manifest = $registry->getManifest('1.2.0');

        $this->line("   • Version API : {$manifest['version']}");
        $this->line("   • Version mobile cible : {$manifest['mobile_version_target']}");
        $this->line("   • Nombre de fonctionnalités : {$manifest['total_features']}");
    }

    private function testSynchronization(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n6. 🔄 Test de synchronisation automatique...");

        try {
            $result = $registry->synchronize();
            $this->line("   • Nouvelles fonctionnalités : {$result['new']}");
            $this->line("   • Mises à jour : {$result['updated']}");
        } catch (\Exception $e) {
            $this->line("   ⚠️ Synchronisation simulée : {$e->getMessage()}");
        }
    }

    private function testCaching(ApiEndpointRegistryInterface $registry): void
    {
        $this->info("\n7. ⚡ Test du système de cache...");

        $stats = $registry->getStatistics();
        $this->line('   • Manifeste en cache : '.($stats['cache_status']['manifest_cached'] ? 'Oui' : 'Non'));

        $registry->invalidateCache();
        $this->line('   ✅ Cache invalidé');
    }
}
