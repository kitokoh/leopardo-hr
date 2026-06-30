<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Contracts;

use App\Modules\Notification\Domain\Models\AppNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function findById(int $id): ?AppNotification;

    /** @return LengthAwarePaginator<int, AppNotification> */
    public function paginateByUser(int $userId, int $perPage = 20): LengthAwarePaginator;

    public function countUnread(int $userId): int;

    public function markAsRead(int $id): void;

    public function markAllAsRead(int $userId): void;

    public function save(AppNotification $notification): AppNotification;
}
