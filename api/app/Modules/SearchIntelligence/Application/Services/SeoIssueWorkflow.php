<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\SearchIntelligence\Domain\Enums\SeoIssueStatus;
use App\Modules\SearchIntelligence\Domain\Exceptions\InvalidTransitionException;
use App\Modules\SearchIntelligence\Domain\Models\SeoIssue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GSI-2 (#8513) — workflow de traitement d'une anomalie SEO :
 * `detected → recommended → approved → applied → verified` (+ `dismissed`).
 *
 * Chaque transition est AUDITÉE (`AuditLog`, société nulle : événement
 * plateforme, pas tenant). L'audit n'est pas décoratif : c'est lui qui rend
 * vérifiable qu'aucune correction n'a été appliquée sans approbation humaine,
 * ce qui est l'exigence centrale de la spec.
 *
 * Toutes les transitions passent par `transition()`, seul point d'écriture du
 * statut : il n'existe pas de chemin qui contourne la machine à états ni
 * l'audit.
 */
final class SeoIssueWorkflow
{
    /**
     * @param  int|null  $actorId  identifiant du super_admin qui agit
     * @param  string|null  $reason  justification — OBLIGATOIRE pour `dismissed`
     *
     * @throws InvalidTransitionException si la transition n'est pas permise ou
     *         si l'écartement est demandé sans justification.
     */
    public function transition(SeoIssue $issue, SeoIssueStatus $target, ?int $actorId, ?string $reason = null): SeoIssue
    {
        $from = $issue->status;

        if (! $from->canTransitionTo($target)) {
            throw new InvalidTransitionException(sprintf(
                'Transition %s → %s refusée pour l’anomalie #%d.',
                $from->value,
                $target->value,
                $issue->id ?? 0,
            ));
        }

        // Un écartement sans motif est un abandon déguisé : refusé, et la
        // contrainte CHECK de la base refuse de son côté.
        if ($target->requiresDismissReason() && ($reason === null || trim($reason) === '')) {
            throw new InvalidTransitionException('Un écartement exige une justification.');
        }

        return DB::transaction(function () use ($issue, $target, $from, $actorId, $reason): SeoIssue {
            $issue->status = $target;

            if ($target === SeoIssueStatus::Applied) {
                $issue->applied_at = Carbon::now();
            }

            if ($target === SeoIssueStatus::Verified) {
                $issue->verified_at = Carbon::now();
                $issue->resolved_at = Carbon::now();
            }

            if ($target === SeoIssueStatus::Dismissed) {
                $issue->dismissed_reason = $reason;
                $issue->resolved_at = Carbon::now();
            }

            $issue->save();

            AuditLog::query()->create([
                'company_id' => null,
                'user_id' => null,
                'action' => 'search_intel.seo_issue.'.$target->value,
                'module' => 'search_intel',
                'auditable_type' => SeoIssue::class,
                'auditable_id' => $issue->id,
                'old_values' => ['status' => $from->value],
                'new_values' => ['status' => $target->value, 'reason' => $reason],
                'metadata' => ['actor_super_admin_id' => $actorId],
            ]);

            return $issue;
        });
    }
}
