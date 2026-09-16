<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Actions;

use App\Modules\Notification\Domain\Models\Notification;

class MarkNotificationsRead
{
    /**
     * Mark specific notifications (or all for the user) as read.
     *
     * @param  int[]|null  $ids  null = mark all unread for the user
     */
    public function execute(int $userId, ?array $ids = null): int
    {
        // #7481 — store canonique (`employee_id`/`is_read`), celui que lit
        // `GET /notifications` : marquer comme lues des lignes d'une AUTRE
        // table laissait la boîte de réception éternellement non lue.
        $query = Notification::query()
            ->where('employee_id', $userId)
            ->where('is_read', false);

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
