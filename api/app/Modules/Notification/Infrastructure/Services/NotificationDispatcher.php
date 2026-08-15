<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\AppNotification;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {}

    public function dispatch(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
    ): AppNotification {
        $notification = AppNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'action_url' => $actionUrl,
            'read' => false,
        ]);

        // QA #2230 : push FCM/APNs câblé — best-effort, ne bloque jamais la
        // création de la notification in-app (le push échoue silencieusement
        // si aucun token actif ou si Firebase n'est pas configuré).
        try {
            $this->sendToUser($userId, $title, (string) $body, $data);
        } catch (\Throwable $e) {
            Log::warning('Push notification dispatch failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    /**
     * Envoie un push à tous les appareils actifs d'un utilisateur.
     * Retourne le nombre de messages envoyés (0 si aucun token ou
     * employé introuvable).
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $employee = Employee::query()->find($userId);
        if ($employee === null) {
            return 0;
        }

        return $this->pushService->sendToEmployee($employee, $title, $body, $data);
    }
}
