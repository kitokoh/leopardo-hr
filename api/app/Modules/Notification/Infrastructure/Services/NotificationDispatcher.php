<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Modules\Notification\Domain\Models\AppNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        // Issue #2498 — la dette #2398 (table `app_notifications` jamais
        // migrée en prod) a rendu les échecs de création in-app totalement
        // invisibles : les try/catch best-effort des appelants loggaient à
        // peine. Journalisation structurée systématique (channel
        // `structured`) sur TOUT échec, sans changer le contrat best-effort
        // (on relance : l'appelant garde son propre comportement, mais la
        // trace est désormais exploitable).
        try {
            $notification = AppNotification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'action_url' => $actionUrl,
                'read' => false,
            ]);
        } catch (Throwable $exception) {
            Log::channel('structured')->error('notification.inapp-create-failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        // Push mobile (FCM) — best-effort : un échec de push ne doit jamais
        // casser la notification in-app (fail-open, journalisé structuré).
        try {
            $this->pushService->sendToUser($userId, $title, (string) $body, $data);
        } catch (Throwable $exception) {
            Log::channel('structured')->warning('notification.push-skipped', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }

        return $notification;
    }
}
