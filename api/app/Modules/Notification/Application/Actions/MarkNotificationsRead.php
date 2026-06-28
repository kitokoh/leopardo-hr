<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Actions;

use App\Modules\Notification\Domain\Models\AppNotification;

class MarkNotificationsRead
{
    /**
     * Mark specific notifications (or all for the user) as read.
     *
     * @param  int[]|null $ids  null = mark all unread for the user
     */
    public function handle(int $userId, ?array $ids = null): int
    {
        $query = AppNotification::query()
            ->where('user_id', $userId)
            ->where('read', false);

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->update([
            'read'    => true,
            'read_at' => now(),
        ]);
    }
}
