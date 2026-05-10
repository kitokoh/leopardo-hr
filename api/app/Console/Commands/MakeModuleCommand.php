<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name : PascalCase module name (e.g. Payroll, Recruitment)}';

    protected $description = 'Generate a DDD module scaffold under app/Modules/{Name}';

    private const DIRECTORIES = [
        'Domain/Models',
        'Domain/ValueObjects',
        'Domain/Events',
        'Domain/Enums',
        'Domain/Exceptions',
        'Application/DTOs',
        'Application/Actions',
        'Application/Queries',
        'Application/Listeners',
        'Infrastructure/Repositories',
        'Infrastructure/Services',
        'Infrastructure/Exports',
        'Interfaces/Api/V1/Controllers',
        'Interfaces/Api/V1/Requests',
        'Interfaces/Api/V1/Resources',
    ];

    public function handle(Filesystem $files): int
    {
        $name = trim($this->argument('name'));

        if (! preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            $this->error("Module name must be PascalCase (e.g. Payroll, Recruitment). Got: {$name}");

            return self::FAILURE;
        }

        $base = app_path("Modules/{$name}");

        if ($files->isDirectory($base)) {
            $this->error("Module {$name} already exists at {$base}");

            return self::FAILURE;
        }

        foreach (self::DIRECTORIES as $dir) {
            $files->makeDirectory("{$base}/{$dir}", 0755, true);
            $files->put("{$base}/{$dir}/.gitkeep", '');
        }

        $routeFile = base_path("routes/modules/" . strtolower($name) . ".php");

        if (! $files->exists($routeFile)) {
            $files->ensureDirectoryExists(dirname($routeFile));
            $files->put($routeFile, $this->routeStub($name));
        }

        $this->info("Module {$name} created at {$base}");
        $this->line("Route file: {$routeFile}");
        $this->line('Next steps:');
        $this->line("  1. Add models in Domain/Models/");
        $this->line("  2. Add controllers in Interfaces/Api/V1/Controllers/");
        $this->line("  3. Register routes in routes/modules/" . strtolower($name) . ".php");
        $this->line("  4. Require the route file in routes/api.php");

        return self::SUCCESS;
    }

    private function routeStub(string $name): string
    {
        $lower = strtolower($name);

        return <<<PHP
<?php

use Illuminate\Support\Facades\Route;

// Module {$name} routes
// Include this file in routes/api.php:
//   require __DIR__.'/modules/{$lower}.php';

Route::middleware(['auth:sanctum', 'tenant'])->prefix('v1/{$lower}')->group(function (): void {
    // Add your routes here
});

PHP;
    }
}
