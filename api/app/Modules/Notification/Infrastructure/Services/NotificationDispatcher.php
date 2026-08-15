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

        // Issue #2200/#2230 — le push FCM est tenté best-effort : un échec
        // de push (réseau, Firebase non configuré, token invalide) ne doit
        // JAMAIS faire échouer la création de la notification in-app.
        $this->attemptPush($userId, $title, $body ?? '', $data);

        return $notification;
    }

    private function attemptPush(int $userId, string $title, string $body, array $data): void
    {
        try {
            $employee = Employee::query()->find($userId);

            if ($employee === null) {
                return;
            }

            $this->pushService->sendToEmployee($employee, $title, $body, $data);
        } catch (\Throwable $exception) {
            Log::warning('Push FCM best-effort failed for notification', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
