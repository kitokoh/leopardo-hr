<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Actions;

use App\Modules\Notification\Domain\Models\AppNotification;
use App\Modules\Notification\Infrastructure\Services\NotificationDispatcher;

class SendNotification
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(
        int    $userId,
        string $type,
        string $title,
        ?string $body = null,
        array  $data = [],
        ?string $actionUrl = null,
    ): AppNotification {
        return $this->dispatcher->dispatch($userId, $type, $title, $body, $data, $actionUrl);
    }
}
