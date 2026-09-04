<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use App\Shared\Contracts\Notification\EmployeeNotifier;
use Illuminate\Support\ServiceProvider;
use App\Contracts\Communication\CommunicationServiceInterface;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Contrat partagé (isolation #5584) : les modules métier notifient
        // via l'interface, jamais par import direct du service.
        $this->app->bind(EmployeeNotifier::class, CommunicationService::class);
    }

    public function boot(): void {}


}