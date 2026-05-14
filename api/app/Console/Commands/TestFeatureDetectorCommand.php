<?php

namespace App\Console\Commands;

use App\Services\FeatureDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class TestFeatureDetectorCommand extends Command
{
    protected $signature = 'features:test-detector {--limit=10 : Limit the number of features to display}';

    protected $description = 'Test the FeatureDetector service and display detected features';

    public function handle(FeatureDetector $detector): int
    {
        $this->info('Testing FeatureDetector service...');

        try {
            $routes = $detector->scanRoutes();
            $this->info('Found '.$routes->count().' API routes');

            $features = $detector->detectNewFeatures();
            $this->info('Detected '.$features->count().' new features');
            $this->displayFeatures($features->take($this->limit()));

            $this->displayEmployeeMetadata($detector);
            $this->displayChanges($detector);

            $this->info('FeatureDetector test completed successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error testing FeatureDetector: '.$e->getMessage());
            $this->error('Trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $features
     */
    private function displayFeatures(Collection $features): void
    {
        if ($features->isEmpty()) {
            return;
        }

        $this->info("\nFirst ".$features->count().' features:');
        $this->table(
            ['Key', 'Title', 'Endpoint', 'Methods', 'UI Type', 'Permissions'],
            $features->map(fn (array $feature): array => $this->featureRow($feature))->toArray()
        );
    }

    private function displayEmployeeMetadata(FeatureDetector $detector): void
    {
        $this->info("\nTesting metadata extraction on EmployeeController::index...");
        $metadata = $detector->extractMetadata(
            'App\Http\Controllers\Api\V1\EmployeeController',
            'index'
        );

        $this->info('Title: '.$this->stringValue($metadata, 'title'));
        $this->info('Description: '.$this->stringValue($metadata, 'description'));
        $this->info('UI Type: '.$this->stringValue($metadata, 'ui_type'));
        $this->info('Mobile Compatible: '.$this->boolLabel($metadata['mobile_compatible'] ?? false));
        $this->info('Permissions: '.implode(', ', $this->stringList($metadata['permissions'] ?? [])));
    }

    private function displayChanges(FeatureDetector $detector): void
    {
        $changes = $detector->detectChanges();
        $this->info("\nFound ".$changes->count().' changes');

        if ($changes->isEmpty()) {
            return;
        }

        $this->warn('Changes detected:');
        foreach ($changes->take(5) as $change) {
            $this->line('- '.$this->stringValue($change, 'type').': '.$this->stringValue($change, 'feature_key'));
        }
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
            $this->stringValue($metadata, 'ui_type'),
            implode(', ', $this->stringList($feature['permissions'] ?? [])),
        ];
    }

    private function limit(): int
    {
        $value = $this->option('limit');

        return is_numeric($value) ? max(1, (int) $value) : 10;
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

    private function boolLabel(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Yes' : 'No';
    }
}
