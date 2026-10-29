<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Infrastructure\Services\PageModerationAuditWriter;
use App\Shared\Contracts\Persistence\TransactionRunner;
use Illuminate\Http\Request;

/**
 * Suspend une Page depuis la console plateforme (BC PAGES, #8729).
 *
 * `Page::suspend()` repasse déjà la Page en brouillon et efface
 * `published_at` — c'est la règle « une suspension retire la Page de
 * l'annuaire, du sitemap et du domaine personnalisé » de #8729. On ne la
 * rejoue pas ici : deux endroits qui décident de la visibilité finissent par
 * ne plus être d'accord.
 *
 * L'audit est écrit dans la **même transaction** que la mutation : soit les
 * deux existent, soit aucun — une Page suspendue sans trace de décision est
 * indéfendable devant un client qui conteste.
 */
final class SuspendPageAction
{
    public function __construct(
        private readonly TransactionRunner $transactions,
        private readonly PageModerationAuditWriter $audit,
    ) {}

    public function execute(Page $page, string $actorId, ?string $actorRole, string $reason, ?Request $request = null): Page
    {
        $before = $this->audit->snapshot($page);

        $this->transactions->run(function () use ($page, $actorId, $actorRole, $reason, $request, $before): void {
            $page->suspend($reason);
            $page->save();

            $this->audit->write(
                $page,
                $actorId,
                $actorRole,
                'page.suspended',
                $reason,
                $before,
                $this->audit->snapshot($page),
                $request,
            );
        });

        return $page;
    }
}
