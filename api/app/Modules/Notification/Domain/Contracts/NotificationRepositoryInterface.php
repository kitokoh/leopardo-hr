<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Contracts;

// #7481 (reliquat) — ce contrat était typé sur `AppNotification` (table
// `app_notifications`), le modèle DÉPRÉCIÉ : le seul contrat de dépôt du module
// Notification désignait donc le store qui n'est plus ni lu ni écrit par l'API.
// Il est retypé sur le store canonique (`Notification`).

use App\Modules\Notification\Domain\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function findById(int $id): ?Notification;

    /** @return LengthAwarePaginator<int, Notification> */
    public function paginateByUser(int $userId, int $perPage = 20): LengthAwarePaginator;

    public function countUnread(int $userId): int;

    public function markAsRead(int $id): void;

    public function markAllAsRead(int $userId): void;

    public function save(Notification $notification): Notification;
}
