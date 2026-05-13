<?php

namespace App\Console\Commands;

use App\Contracts\FeatureDetectorInterface;
use Illuminate\Console\Command;

/**
 * Detecte les nouvelles fonctionnalites API exposees par les routes Laravel.
 */
class DetectFeaturesCommand extends Command
{
    protected $signature = 'features:detect
                            {--dry-run : Afficher les fonctionnalites detectees sans les enregistrer}
                            {--details : Afficher des informations detaillees}';

    protected $description = 'Detecte automatiquement les nouvelles fonctionnalites API';

    public function handle(FeatureDetectorInterface $detector): int
    {
        $this->info('Detection des nouvelles fonctionnalites API...');

        try {
            $routes = $detector->scanRoutes();
            $this->info($routes->count().' routes API trouvees');

            if ($this->optionBool('details')) {
                $this->table(
                    ['URI', 'Methodes', 'Controleur', 'Action'],
                    $routes->map(fn (array $route): array => $this->routeRow($route))->toArray()
                );
            }

            $newFeatures = $detector->detectNewFeatures();
            if ($newFeatures->isEmpty()) {
                $this->info('Aucune nouvelle fonctionnalite detectee');

                return self::SUCCESS;
            }

            $this->info($newFeatures->count().' nouvelles fonctionnalites detectees');
            $this->table(
                ['Cle', 'Titre', 'Endpoint', 'Methodes', 'Type UI'],
                $newFeatures->map(fn (array $feature): array => $this->featureRow($feature))->toArray()
            );

            if ($this->optionBool('dry-run')) {
                $this->warn('Mode dry-run : aucune fonctionnalite n\'a ete enregistree');

                return self::SUCCESS;
            }

            $this->warn('Enregistrement des fonctionnalites a brancher dans le Feature Registry.');
            $this->displayChanges($detector);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Erreur lors de la detection : '.$e->getMessage());

            if ($this->optionBool('details')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $route
     * @return array<int, string>
     */
    private function routeRow(array $route): array
    {
        return [
            $this->stringValue($route, 'uri'),
            implode(', ', $this->stringList($route['methods'] ?? [])),
            class_basename($this->stringValue($route, 'controller_class')),
            $this->stringValue($route, 'method'),
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<int, string>
     */
    private function featureRow(array $feature): array
    {
        $metadata = is_array($feature['metadata'] ?? null) ? $feature['metadata'] : [];

        return [
            $this->stringValue($feature, 'key'),
            $this->stringValue($feature, 'title'),
            $this->stringValue($feature, 'endpoint'),
            implode(', ', $this->stringList($feature['http_methods'] ?? [])),
            $this->stringValue($metadata, 'ui_type', 'generic'),
        ];
    }

    private function displayChanges(FeatureDetectorInterface $detector): void
    {
        $changes = $detector->detectChanges();
        if ($changes->isEmpty()) {
            return;
        }

        $this->info($changes->count().' changements detectes dans les fonctionnalites existantes');
        foreach ($changes as $change) {
            $type = $this->stringValue($change, 'type');
            $featureKey = $this->stringValue($change, 'feature_key');
            $this->line("- {$type}: {$featureKey}");
        }
    }

    private function optionBool(string $key): bool
    {
        return filter_var($this->option($key), FILTER_VALIDATE_BOOL);
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
}
