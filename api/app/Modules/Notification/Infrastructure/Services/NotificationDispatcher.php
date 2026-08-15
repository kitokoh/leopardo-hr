<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Jobs\SendPushNotificationJob;
use App\Modules\Notification\Domain\Models\AppNotification;
use Illuminate\Support\Facades\Log;

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

        // Issue #2252 — push FCM/APNs : les tokens device sont enregistrés
        // par employé (`/device-tokens`) ; on dispatche le job tenant-scoped
        // `SendPushNotificationJob` (no-op propre si aucun token actif ou si
        // Firebase n'est pas configuré — PushNotificationService::sendToTokens).
        // Best-effort : un échec de dispatch ne doit jamais casser la
        // notification in-app (pattern emailBestEffort de NotifyTaxRateValidation).
        try {
            $metadata = $data;
            if (is_string($actionUrl) && $actionUrl !== '') {
                $metadata['action_url'] = $actionUrl;
            }

            SendPushNotificationJob::dispatch($userId, $title, (string) $body, $metadata);
        } catch (\Throwable $e) {
            Log::warning('notification.push-dispatch-failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }
}
