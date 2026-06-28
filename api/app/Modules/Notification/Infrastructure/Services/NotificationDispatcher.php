<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Modules\Notification\Domain\Models\AppNotification;

class NotificationDispatcher
{
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

        // TODO: push to FCM/APNs via PushNotificationService when device tokens exist
        // $this->pushService->sendToUser($userId, $title, $body);

        return $notification;
    }
}
