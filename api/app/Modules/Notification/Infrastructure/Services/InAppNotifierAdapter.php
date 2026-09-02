<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Services;

use App\Core\Notifications\Contracts\InAppNotifier;

/**
 * Adaptateur du contrat transversal `InAppNotifier` vers le dispatch de
 * notifications in-app du module Notification (BC-13).
 *
 * Enregistré dans `App\Providers\AppServiceProvider` : les modules métier
 * type-hintent `InAppNotifier` (Core) sans importer le module Notification
 * (garde cross-module #5584). Le push mobile reste best-effort (géré par
 * NotificationDispatcher).
 */
final class InAppNotifierAdapter implements InAppNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
    ): void {
        $this->dispatcher->dispatch($userId, $type, $title, $body, $data, $actionUrl);
    }
}
