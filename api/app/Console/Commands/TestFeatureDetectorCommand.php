<?php

namespace App\Console\Commands;

use App\Services\FeatureDetector;
use Illuminate\Console\Command;

class TestFeatureDetectorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'features:test-detector {--limit=10 : Limit the number of features to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the FeatureDetector service and display detected features';

    /**
     * Execute the console command.
     */
    public function handle(FeatureDetector $detector): int
    {
        $this->info('Testing FeatureDetector service...');
        
        try {
            // Test scanRoutes
            $this->info('Scanning API routes...');
            $routes = $detector->scanRoutes();
            $this->info("Found {$routes->count()} API routes");
            
            // Test detectNewFeatures
            $this->info('Detecting new features...');
            $features = $detector->detectNewFeatures();
            $this->info("Detected {$features->count()} new features");
            
            // Display limited features
            $limit = (int) $this->option('limit');
            $displayFeatures = $features->take($limit);
            
            if ($displayFeatures->count() > 0) {
                $this->info("\nFirst {$displayFeatures->count()} features:");
                
                $headers = ['Key', 'Title', 'Endpoint', 'Methods', 'UI Type', 'Permissions'];
                $rows = [];
                
                foreach ($displayFeatures as $feature) {
                    $rows[] = [
                        $feature['key'],
                        $feature['title'],
                        $feature['endpoint'],
                        implode(', ', $feature['http_methods']),
                        $feature['metadata']['ui_type'],
                        implode(', ', $feature['permissions']),
                    ];
                }
                
                $this->table($headers, $rows);
            }
            
            // Test extractMetadata on a specific controller
            $this->info("\nTesting metadata extraction on EmployeeController::index...");
            $metadata = $detector->extractMetadata(
                'App\Http\Controllers\Api\V1\EmployeeController',
                'index'
            );
            
            $this->info("Title: {$metadata['title']}");
            $this->info("Description: {$metadata['description']}");
            $this->info("UI Type: {$metadata['ui_type']}");
            $this->info("Mobile Compatible: " . ($metadata['mobile_compatible'] ? 'Yes' : 'No'));
            $this->info("Permissions: " . implode(', ', $metadata['permissions']));
            
            // Test detectChanges
            $this->info("\nDetecting changes in existing features...");
            $changes = $detector->detectChanges();
            $this->info("Found {$changes->count()} changes");
            
            if ($changes->count() > 0) {
                $this->warn("Changes detected:");
                foreach ($changes->take(5) as $change) {
                    $this->line("- {$change['type']}: {$change['feature_key']}");
                }
            }
            
            $this->info("\n✅ FeatureDetector test completed successfully!");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Error testing FeatureDetector: {$e->getMessage()}");
            $this->error("Trace: {$e->getTraceAsString()}");
            
            return Command::FAILURE;
        }
    }
}