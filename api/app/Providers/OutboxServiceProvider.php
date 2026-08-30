<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Outbox\Application\Services\OutboxPublisher;
use App\Core\Outbox\Infrastructure\Services\OutboxConsumerRegistry;
use App\Core\Outbox\Infrastructure\Services\OutboxDispatcher;
use App\Modules\Platform\Infrastructure\Services\PlatformEventOutboxConsumer;
use Illuminate\Support\ServiceProvider;

/**
 * MAT-008 (#5866) — Runtime inbox/outbox/queues fiable (BC-01 PLATFORM).
 *
 * Enregistre les services de l'outbox générique (publisher, registre de
 * consommateurs, dispatcher) et le consommateur de référence des événements
 * de plateforme (audit `outbox.delivered`).
 */
class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxPublisher::class);
        $this->app->singleton(OutboxConsumerRegistry::class);
        $this->app->singleton(OutboxDispatcher::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(OutboxConsumerRegistry::class);
        $registry->register($this->app->make(PlatformEventOutboxConsumer::class));
    }
}
