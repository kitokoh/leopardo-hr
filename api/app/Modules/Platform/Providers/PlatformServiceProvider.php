<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\Infrastructure\Services\ScheduledTaskRunRecorder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Platform module contracts here
    }

    public function boot(): void
    {
        // PA2-QA-006 — record last start/finish outcome of every scheduled
        // Artisan command so the platform admin "System" screen can surface
        // it (queue depth / failed jobs already exist; this adds "last run").
        // Only fires while `schedule:run` executes, never from web requests.
        $this->app->singleton(ScheduledTaskRunRecorder::class);

        Event::listen(ScheduledTaskStarting::class, [ScheduledTaskRunRecorder::class, 'onStarting']);
        Event::listen(ScheduledTaskFinished::class, [ScheduledTaskRunRecorder::class, 'onFinished']);
        Event::listen(ScheduledTaskFailed::class, [ScheduledTaskRunRecorder::class, 'onFailed']);
    }
}
