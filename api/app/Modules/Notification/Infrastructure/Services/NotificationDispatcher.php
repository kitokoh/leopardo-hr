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
        int    $userId,
        string $type,
        string $title,
        ?string $body = null,
        array  $data = [],
        ?string $actionUrl = null,
    ): AppNotification {
        // Issue #2498 — observabilité : un échec de persistance in-app doit
        // être traçable (log structuré) AVANT de remonter, jamais silencieux
        // (dette #1813 : la table manquante a avalé des notifications en prod).
        try {
            $notification = AppNotification::create([
                'user_id'    => $userId,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'data'       => $data,
                'action_url' => $actionUrl,
                'read'       => false,
            ]);
        } catch (Throwable $exception) {
            Log::channel('structured')->error('notification.dispatch-failed', [
                'event'             => 'notification.dispatch',
                'notification_type' => $type,
                'user_id'           => $userId,
                'error'             => $exception->getMessage(),
                'exception_class'   => $exception::class,
            ]);

            throw $exception;
        }

        // Push mobile (FCM) — best-effort : un échec de push ne doit jamais
        // casser la notification in-app (fail-open, journalisé structuré).
        try {
            $this->pushService->sendToUser($userId, $title, (string) $body, $data);
        } catch (Throwable $exception) {
            Log::channel('structured')->warning('notification.push-skipped', [
                'event'             => 'notification.push',
                'notification_type' => $type,
                'user_id'           => $userId,
                'error'             => $exception->getMessage(),
                'exception_class'   => $exception::class,
            ]);
        }

        return $notification;
    }
}
