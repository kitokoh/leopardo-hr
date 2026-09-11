<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Contracts\Communication\CommunicationServiceInterface;
use App\Modules\Notification\Domain\Support\NotifyTeamToolCatalog;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Modules\Notification\Infrastructure\Services\PushNotificationService;
use App\Shared\Contracts\Notification\EmployeeNotifier;
use App\Shared\Contracts\Notification\PushNotifier;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contrat partagé (isolation #5584) : les modules métier notifient
        // via l'interface, jamais par import direct du service.
        $this->app->bind(EmployeeNotifier::class, CommunicationService::class);
        $this->app->bind(PushNotifier::class, PushNotificationService::class);
        $this->app->bind(CommunicationServiceInterface::class, CommunicationService::class);
    }

    public function boot(): void
    {
        // B3c (#6858) — déclaration de l'outil d'envoi `notify_team` au
        // contrat A3 (BC-23, #6850) : l'hôte ToolRegistry enrichit l'entrée
        // ai_tool_registry homonyme au boot. Garde d'idempotence (#6947) :
        // AIToolDefinitionRegistry est un collecteur statique re-booté à
        // chaque requête (PHP-FPM) et à chaque test.
        foreach (NotifyTeamToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
