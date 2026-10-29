<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Infrastructure\Services\PageModerationAuditWriter;
use App\Shared\Contracts\Persistence\TransactionRunner;
use Illuminate\Http\Request;

/**
 * Lève une suspension plateforme (BC PAGES, #8729).
 *
 * La Page revient en **brouillon** : c'est au tenant de republier
 * consciemment. Republier à sa place écraserait un choix éditorial qui date
 * d'avant la suspension — le blocage a pu lui faire changer de contenu.
 */
final class UnsuspendPageAction
{
    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly PageModerationAuditWriter $audit,
    ) {}

    public function execute(Page $page, string $actorId, ?string $actorRole, string $reason, ?Request $request = null): Page
    {
        $before = $this->audit->snapshot($page);

        $this->transactions->run(function () use ($page, $actorId, $actorRole, $reason, $request, $before): void {
            $page->unsuspend();
            $page->save();

            $this->audit->write(
                $page,
                $actorId,
                $actorRole,
                'page.unsuspended',
                $reason,
                $before,
                $this->audit->snapshot($page),
                $request,
            );
        });

        return $page;
    }
}
