<?php

declare(strict_types=1);

namespace App\Modules\Cameras\Providers;

use Illuminate\Support\ServiceProvider;

class CamerasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Cameras module contracts here
        // e.g. $this->app->bind(CameraRepositoryInterface::class, EloquentCameraRepository::class);
    }

    public function boot(): void
    {
        // Boot Cameras module — routes loaded via Infrastructure/routes or Interfaces/Api/V1
    }
}
