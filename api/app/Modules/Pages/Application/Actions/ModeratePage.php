<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageModerationAudit;
use App\Shared\Contracts\Persistence\TransactionRunner;
use Illuminate\Http\Request;

/**
 * Décisions de modération plateforme sur une Page (BC PAGES, #8729).
 *
 * ## Un seul chemin d'écriture, et il écrit TOUJOURS l'audit
 *
 * Suspendre, lever une suspension, approuver/rejeter un dossier de
 * vérification : quatre gestes, un seul invariant — **aucune décision sans
 * ligne d'audit**. Si l'audit était écrit par les contrôleurs, le cinquième
 * contrôleur ajouté dans six mois l'oublierait. Ici, l'audit est dans la même
 * transaction que la mutation : soit les deux existent, soit aucune.
 *
 * ## Le motif est obligatoire et validé par le domaine
 *
 * `Page::suspend()` refuse un motif hors `SUSPENSION_REASONS` ;
 * `PageVerificationService::decide()` refuse un motif hors
 * `DECISION_REASONS`. On ne recopie pas ces listes ici : une liste recopiée
 * diverge au premier ajout, et c'est la copie oubliée qui laisse passer un
 * motif inventé.
 *
 * ## Ce que l'audit ne contient pas
 *
 * Ni contenu de pièce, ni code SMS, ni donnée personnelle du tenant. L'état
 * avant/après se limite aux champs de statut — de quoi reconstituer la
 * décision, pas de quoi reconstituer le dossier.
 */
final class ModeratePage
{
    public function __construct(
        private readonly TransactionRunner $transactions,
    ) {}

    /**
     * Suspend une Page : elle quitte l'annuaire, le sitemap et son domaine.
     *
     * `Page::suspend()` repasse déjà la Page en brouillon et efface
     * `published_at` — c'est la règle « une suspension retire la Page de
     * l'annuaire, du sitemap et du domaine personnalisé » de #8729. On ne la
     * rejoue pas ici : deux endroits qui décident de la visibilité finissent
     * par ne plus être d'accord.
     */
    public function suspend(Page $page, string $actorId, ?string $actorRole, string $reason, Request $request): Page
    {
        $before = $this->snapshot($page);

        $this->transactions->run(function () use ($page, $actorId, $actorRole, $reason, $request, $before): void {
            $page->suspend($reason);
            $page->save();

            $this->audit($page, $actorId, $actorRole, 'page.suspended', $reason, $before, $this->snapshot($page), $request);
        });

        return $page;
    }

    /**
     * Lève une suspension. La Page revient en **brouillon** : c'est au tenant
     * de republier consciemment, on ne republie jamais à sa place.
     */
    public function unsuspend(Page $page, string $actorId, ?string $actorRole, string $reason, Request $request): Page
    {
        $before = $this->snapshot($page);

        $this->transactions->run(function () use ($page, $actorId, $actorRole, $reason, $request, $before): void {
            $page->unsuspend();
            $page->save();

            $this->audit($page, $actorId, $actorRole, 'page.unsuspended', $reason, $before, $this->snapshot($page), $request);
        });

        return $page;
    }

    /**
     * Trace l'accès à une pièce justificative.
     *
     * L'exigence #8729 est « les pièces ne sont accessibles qu'aux rôles
     * plateforme autorisés et **leur accès est tracé** ». Une lecture n'est pas
     * une décision, mais elle est tout aussi sensible : c'est le moment où un
     * opérateur voit le bail d'un tiers.
     */
    public function recordEvidenceAccess(Page $page, string $actorId, ?string $actorRole, int $evidenceId, Request $request): void
    {
        $this->audit(
            $page,
            $actorId,
            $actorRole,
            'page.evidence.accessed',
            'moderation_review',
            null,
            ['evidence_id' => $evidenceId],
            $request,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Page $page): array
    {
        return [
            'status' => $page->status->value,
            'published_at' => $page->published_at?->toIso8601String(),
            'verification_status' => $page->verification_status->value,
            'suspended_at' => $page->suspended_at?->toIso8601String(),
            'suspension_reason' => $page->suspension_reason,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(
        Page $page,
        string $actorId,
        ?string $actorRole,
        string $action,
        string $reason,
        ?array $before,
        ?array $after,
        Request $request,
    ): void {
        PageModerationAudit::query()->create([
            'company_id' => $page->company_id,
            'page_id' => $page->id,
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'request_id' => function_exists('correlation_id') ? correlation_id() : null,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
