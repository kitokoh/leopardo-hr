<?php

namespace App\Console\Commands;

use App\Contracts\FeatureRegistryInterface;
use App\Models\Feature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Demonstration du registre central des fonctionnalites API.
 */
class DemoFeatureRegistryCommand extends Command
{
    protected $signature = 'features:demo
                            {--reset : Reinitialiser les donnees de demonstration}
                            {--mobile-version=1.0.0 : Version mobile pour les tests}';

    protected $description = 'Demonstration complete du systeme Feature Registry';

    public function handle(FeatureRegistryInterface $registry): int
    {
        $this->info('Demonstration du Feature Registry');
        $this->newLine();

        if ($this->optionBool('reset')) {
            $this->resetDemoData();
        }

        $this->info('1. Creation de fonctionnalites d\'exemple...');
        $this->createDemoFeatures($registry);

        $this->info('2. Statistiques du registre:');
        $this->displayStatistics($registry);

        $this->info('3. Test de recuperation des fonctionnalites:');
        $this->testFeatureRetrieval($registry);

        $this->info('4. Test de compatibilite mobile:');
        $this->testMobileCompatibility($registry);

        $this->info('5. Generation du manifeste:');
        $this->generateAndDisplayManifest($registry);

        $this->info('6. Test de synchronisation:');
        $this->testSynchronization($registry);

        $this->info('7. Test du systeme de cache:');
        $this->testCaching($registry);

        $this->newLine();
        $this->info('Demonstration terminee avec succes.');

        return Command::SUCCESS;
    }

    private function resetDemoData(): void
    {
        $this->warn('Suppression des donnees de demonstration...');

        DB::table('features')->where('key', 'like', 'demo_%')->delete();

        $this->info('Donnees supprimees.');
        $this->newLine();
    }

    private function createDemoFeatures(FeatureRegistryInterface $registry): void
    {
        foreach ($this->demoFeatures() as $featureData) {
            $feature = new Feature($featureData);
            $registry->registerFeature($feature);

            $this->line('  OK '.$feature->title.' enregistree');
        }

        $this->newLine();
    }

    private function displayStatistics(FeatureRegistryInterface $registry): void
    {
        $stats = $registry->getStatistics();

        $this->table(
            ['Metrique', 'Valeur'],
            [
                ['Total des fonctionnalites', $this->statInt($stats, 'total_features')],
                ['Fonctionnalites actives', $this->statInt($stats, 'active_features')],
                ['Fonctionnalites inactives', $this->statInt($stats, 'inactive_features')],
                ['Mises a jour recentes', $this->statInt($stats, 'recently_updated')],
            ]
        );

        $byStatus = $this->statArray($stats, 'by_status');
        if ($byStatus !== []) {
            $this->info('Par statut:');
            foreach ($byStatus as $status => $count) {
                $this->line('  - '.(string) $status.': '.(string) $count);
            }
        }

        $this->newLine();
    }

    private function testFeatureRetrieval(FeatureRegistryInterface $registry): void
    {
        $allFeatures = $registry->getFeatures();
        $this->line('  Total des fonctionnalites: '.$allFeatures->count());

        $specificFeature = $registry->getFeature('demo_employee_management');
        if ($specificFeature instanceof Feature) {
            $this->line('  Fonctionnalite trouvee: '.$specificFeature->title);
        }

        $exists = $registry->hasFeature('demo_employee_management');
        $this->line('  Fonctionnalite existe: '.$this->boolLabel($exists));

        $this->newLine();
    }

    private function testMobileCompatibility(FeatureRegistryInterface $registry): void
    {
        $mobileVersion = $this->optionString('mobile-version', '1.0.0');

        $compatibleFeatures = $registry->getCompatibleFeatures($mobileVersion);
        $this->line('  Fonctionnalites compatibles avec v'.$mobileVersion.': '.$compatibleFeatures->count());

        foreach ($compatibleFeatures as $feature) {
            $maxVersion = $feature->mobile_version_max ? ' - '.$feature->mobile_version_max : '';
            $this->line('    - '.$feature->title.' (v'.$feature->mobile_version_min.$maxVersion.')');
        }

        $oldCompatible = $registry->getCompatibleFeatures('1.0.0');
        $this->line('  Fonctionnalites compatibles avec v1.0.0: '.$oldCompatible->count());

        $this->newLine();
    }

    private function generateAndDisplayManifest(FeatureRegistryInterface $registry): void
    {
        $mobileVersion = $this->optionString('mobile-version', '1.0.0');
        $manifest = $registry->getManifest($mobileVersion);

        $this->line('  Manifeste genere pour la version mobile '.$mobileVersion.':');
        $this->line('    - Version API: '.$this->stringValue($manifest, 'version'));
        $this->line('    - Genere le: '.$this->stringValue($manifest, 'generated_at'));
        $this->line('    - Nombre de fonctionnalites: '.$this->statInt($manifest, 'total_features'));

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->info('Detail des fonctionnalites:');

            $rows = [];
            foreach ($this->statArray($manifest, 'features') as $feature) {
                if (is_array($feature)) {
                    $rows[] = $this->manifestFeatureRow($feature);
                }
            }

            $this->table(['Cle', 'Titre', 'Endpoint', 'Methodes', 'Permissions'], $rows);
        }

        $this->newLine();
    }

    private function testSynchronization(FeatureRegistryInterface $registry): void
    {
        $this->line('  Lancement de la synchronisation...');

        $result = $registry->synchronize();

        $this->line('    - Nouvelles fonctionnalites: '.$result['new']);
        $this->line('    - Fonctionnalites mises a jour: '.$result['updated']);
        $this->line('    - Fonctionnalites supprimees: '.$result['removed']);

        if ($result['errors'] !== []) {
            $this->warn('    - Erreurs: '.count($result['errors']));
            foreach ($result['errors'] as $error) {
                $this->line('      - '.$error);
            }
        } else {
            $this->line('    OK Aucune erreur');
        }

        $this->newLine();
    }

    private function testCaching(FeatureRegistryInterface $registry): void
    {
        $this->line('  Test du cache...');

        $start = microtime(true);
        $features1 = $registry->getFeatures();
        $time1 = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        $features2 = $registry->getFeatures();
        $time2 = round((microtime(true) - $start) * 1000, 2);

        $this->line('    - Premier appel: '.$time1.'ms ('.$features1->count().' fonctionnalites)');
        $this->line('    - Deuxieme appel: '.$time2.'ms ('.$features2->count().' fonctionnalites)');

        if ($time2 < $time1 && $time1 > 0.0) {
            $improvement = round(($time1 - $time2) / $time1 * 100, 1);
            $this->line('    OK Cache fonctionnel (amelioration: '.$improvement.'%)');
        }

        $registry->invalidateCache();
        $this->line('    Cache invalide');

        $this->newLine();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoFeatures(): array
    {
        return [
            [
                'key' => 'demo_employee_management',
                'title' => 'Gestion des Employes (Demo)',
                'description' => 'Creer, modifier et gerer les employes de l\'entreprise',
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
                            ['name' => 'first_name', 'type' => 'text', 'label' => 'Prenom', 'required' => true],
                            ['name' => 'last_name', 'type' => 'text', 'label' => 'Nom', 'required' => true],
                            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'demo_attendance_tracking',
                'title' => 'Suivi des Presences (Demo)',
                'description' => 'Enregistrer et consulter les heures de presence',
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
                'title' => 'Rapports Avances (Demo)',
                'description' => 'Generation de rapports detailles et analytics',
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
                'mobile_version_min' => '1.5.0',
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
                'title' => 'Fonctionnalite Heritee (Demo)',
                'description' => 'Ancienne fonctionnalite en cours de depreciation',
                'endpoint' => '/api/v1/legacy/old-feature',
                'http_methods' => ['GET'],
                'parameters' => [],
                'response_schema' => ['data' => 'object'],
                'permissions' => ['legacy.access'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => '1.2.0',
                'api_version' => 'v1',
                'status' => 'deprecated',
                'metadata' => [
                    'ui_type' => 'generic',
                    'deprecation_notice' => 'Cette fonctionnalite sera supprimee dans la version 2.0',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<int, string>
     */
    private function manifestFeatureRow(array $feature): array
    {
        return [
            $this->stringValue($feature, 'key'),
            $this->stringValue($feature, 'title'),
            $this->stringValue($feature, 'endpoint'),
            implode(', ', $this->stringList($feature['methods'] ?? [])),
            implode(', ', $this->stringList($feature['permissions'] ?? [])),
        ];
    }

    private function optionBool(string $key): bool
    {
        return filter_var($this->option($key), FILTER_VALIDATE_BOOL);
    }

    private function optionString(string $key, string $default): string
    {
        $value = $this->option($key);

        if ($value === null || $value === false || is_array($value)) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
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
     * @return array<array-key, mixed>
     */
    private function statArray(array $stats, string $key): array
    {
        $value = $stats[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stringValue(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map('strval', $value));
    }

    private function boolLabel(bool $value): string
    {
        return $value ? 'Oui' : 'Non';
    }
}
