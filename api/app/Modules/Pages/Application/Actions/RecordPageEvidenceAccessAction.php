<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Infrastructure\Services\PageModerationAuditWriter;
use Illuminate\Http\Request;

/**
 * Trace l'accès d'un opérateur plateforme à une pièce justificative
 * (BC PAGES, #8729).
 *
 * ## Une lecture est une décision de traitement, pas un simple GET
 *
 * #8729 exige que les pièces ne soient accessibles qu'aux rôles plateforme
 * autorisés **et que leur accès soit tracé** : c'est le moment où un opérateur
 * voit le bail d'un tiers. Une lecture non tracée ne se voit pas en
 * production — elle se constate en audit, trop tard.
 *
 * ## Pas de transaction
 *
 * Il n'y a aucune mutation à protéger : on écrit une ligne, point. Envelopper
 * une écriture unique dans `TransactionRunner` ajouterait un BEGIN/COMMIT sans
 * changer l'atomicité réelle, et donnerait l'illusion d'un invariant qu'on ne
 * tient pas.
 */
final class RecordPageEvidenceAccessAction
{
    public function __construct(
        private readonly PageModerationAuditWriter $audit,
    ) {}

    public function execute(Page $page, string $actorId, ?string $actorRole, int $evidenceId, ?Request $request = null): void
    {
        $this->audit->write(
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
}
