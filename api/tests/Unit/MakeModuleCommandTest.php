<?php

namespace Tests\Unit;

use Tests\TestCase;

class MakeModuleCommandTest extends TestCase
{
    public function test_make_module_creates_directory_structure(): void
    {
        $moduleName = 'TestModule' . time();
        $basePath = app_path("Modules/{$moduleName}");

        try {
            $this->artisan("make:module {$moduleName}")
                ->assertExitCode(0);

            $expectedDirs = [
                'Domain/Models',
                'Domain/Events',
                'Domain/Enums',
                'Domain/Exceptions',
                'Domain/ValueObjects',
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

            foreach ($expectedDirs as $dir) {
                $this->assertDirectoryExists("{$basePath}/{$dir}");
            }

            $routeFile = base_path('routes/modules/' . strtolower($moduleName) . '.php');
            $this->assertFileExists($routeFile);
        } finally {
            // Cleanup
            if (is_dir($basePath)) {
                $this->deleteDirectory($basePath);
            }
            $routeFile = base_path('routes/modules/' . strtolower($moduleName) . '.php');
            if (file_exists($routeFile)) {
                unlink($routeFile);
            }
        }
    }

    public function test_make_module_rejects_invalid_name(): void
    {
        $this->artisan('make:module invalid-name')
            ->assertExitCode(1);
    }

    public function test_make_module_rejects_duplicate(): void
    {
        $this->artisan('make:module Cameras')
            ->assertExitCode(1);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
