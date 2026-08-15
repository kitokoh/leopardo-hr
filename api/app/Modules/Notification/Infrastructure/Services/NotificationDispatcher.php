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
        $notification = AppNotification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'data'       => $data,
            'action_url' => $actionUrl,
            'read'       => false,
        ]);

        // Push mobile (FCM) — best-effort : un échec de push ne doit jamais
        // casser la notification in-app (fail-open, journalisé).
        try {
            $this->pushService->sendToUser($userId, $title, (string) $body, $data);
        } catch (Throwable $exception) {
            Log::warning('Push notification skipped after in-app dispatch', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }

        return $notification;
    }
}
