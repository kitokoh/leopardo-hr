<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpStep;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use App\Modules\Communication\Infrastructure\Jobs\SendCommunicationFollowUpJob;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Materialisation des echeances de relance d'UN tenant (BC-29
 * COMMUNICATION, R4 #7689 — spec §3.4).
 *
 * Pour chaque regle ACTIVE d'une boite ACTIVE :
 * 1. candidats = fils dont le DERNIER message est SORTANT (envoye par la
 *    boite) — « si pas de reponse a un fil sortant apres N jours » ;
 * 2. l'etape due (delais cumules depuis le message sortant ancre, ou depuis
 *    la relance precedente `sent`) est inseree dans la file
 *    `communication_follow_ups` (UNIQUE company×thread×rule×position :
 *    DEDUPLICATION par construction — rejouer la passe ne cree rien) ;
 * 3. chaque echeance `pending` arrivee a maturite part en
 *    `SendCommunicationFollowUpJob` (queue `communication`) — TOUS les
 *    garde-fous sont re-evalues dans le job juste avant l'envoi.
 *
 * IDEMPOTENT par construction : la commande schedulee peut etre rejouee
 * sans risque de double relance (exigence issue).
 */
class CommunicationFollowUpScheduler
{
    /**
     * Materialise puis dispatche les echeances dues du tenant COURANT
     * (contexte tenant deja etabli par l'appelant). Retourne le nombre de
     * jobs dispatches.
     */
    public function run(string $companyId): int
    {
        $rules = CommunicationFollowUpRule::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->with(['steps', 'integration'])
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $integration = $rule->integration;

            if ($integration === null || ! $integration->isActive()) {
                continue;
            }

            $this->materializeRule($rule, $integration);
        }

        return $this->dispatchDue($companyId);
    }

    /**
     * Cree (au plus) la prochaine echeance due de chaque fil candidat.
     */
    private function materializeRule(CommunicationFollowUpRule $rule, CommunicationIntegration $integration): void
    {
        /** @var list<CommunicationFollowUpStep> $steps */
        $steps = $rule->steps->all();

        if ($steps === []) {
            return;
        }

        $mailbox = mb_strtolower((string) $integration->email);

        $threads = CommunicationThread::query()
            ->withoutGlobalScopes()
            ->where('company_id', $rule->company_id)
            ->where('integration_id', $integration->id)
            ->orderByDesc('last_message_at')
            ->get();

        foreach ($threads as $thread) {
            $anchor = $this->outboundAnchor($thread, $mailbox);

            if ($anchor === null) {
                continue;
            }

            $this->materializeThread($rule, $integration, $thread, $anchor, $steps);
        }
    }

    /**
     * Dernier message « humain » du fil s'il est SORTANT (envoye par la
     * boite) — sinon null. Les auto-reponses (RFC 3834) ne comptent pas
     * comme le dernier mot du destinataire (l'ancre reste le sortant — le
     * garde-fou `auto_reply` gele ensuite la sequence a l'envoi) ; les fils
     * de liste de diffusion sont exclus d'emblee.
     */
    private function outboundAnchor(CommunicationThread $thread, string $mailbox): ?CommunicationMessage
    {
        $recent = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('thread_id', $thread->id)
            ->whereNotNull('sent_at')
            ->orderByDesc('sent_at')
            ->limit(25)
            ->get();

        if ($recent->contains(fn (CommunicationMessage $message): bool => $message->is_list_message)) {
            return null;
        }

        /** @var CommunicationMessage|null $last */
        $last = $recent->first(
            fn (CommunicationMessage $message): bool => ! $message->is_auto_reply
        );

        if ($last === null
            || $last->from_email === null
            || mb_strtolower($last->from_email) !== $mailbox) {
            return null;
        }

        return $last;
    }

    /**
     * @param  list<CommunicationFollowUpStep>  $steps
     */
    private function materializeThread(
        CommunicationFollowUpRule $rule,
        CommunicationIntegration $integration,
        CommunicationThread $thread,
        CommunicationMessage $anchor,
        array $steps,
    ): void {
        $contactEmail = $this->recipientOf($anchor, mb_strtolower((string) $integration->email));

        if ($contactEmail === null) {
            return;
        }

        $existing = CommunicationFollowUp::query()
            ->withoutGlobalScopes()
            ->where('company_id', $rule->company_id)
            ->where('thread_id', $thread->id)
            ->where('rule_id', $rule->id)
            ->orderBy('step_position')
            ->get()
            ->keyBy('step_position');

        // Reference temporelle de la prochaine etape : envoi de la relance
        // precedente, sinon le message sortant ancre.
        $reference = $anchor->sent_at;

        foreach ($steps as $step) {
            /** @var CommunicationFollowUp|null $line */
            $line = $existing->get($step->position);

            if ($line !== null) {
                if ($line->status !== CommunicationFollowUp::STATUS_SENT) {
                    // Echeance encore ouverte (pending) ou terminee
                    // (skipped/cancelled/failed) : la sequence s'arrete la.
                    return;
                }

                $reference = $line->sent_at ?? $reference;

                continue;
            }

            if ($reference === null) {
                return;
            }

            $due = Carbon::parse($reference)->addDays($step->delay_days);

            if ($due->isFuture()) {
                // Pas encore mure — les etapes suivantes non plus.
                return;
            }

            try {
                $followUp = new CommunicationFollowUp;
                $followUp->forceFill([
                    'company_id' => $rule->company_id,
                    'rule_id' => $rule->id,
                    'integration_id' => $integration->id,
                    'thread_id' => $thread->id,
                    'message_id' => $anchor->id,
                    'step_position' => $step->position,
                    'contact_email' => $contactEmail,
                    'scheduled_for' => $due,
                    'status' => CommunicationFollowUp::STATUS_PENDING,
                ]);
                $followUp->save();
            } catch (UniqueConstraintViolationException) {
                // Passe concurrente : l'echeance existe deja (deduplication).
            }

            // Une seule nouvelle echeance par passe et par fil : l'etape
            // suivante attendra que celle-ci soit `sent`.
            return;
        }
    }

    /**
     * Destinataire de la relance : premier destinataire du message sortant
     * ancre qui n'est pas la boite elle-meme.
     */
    private function recipientOf(CommunicationMessage $anchor, string $mailbox): ?string
    {
        foreach ($anchor->to_emails ?? [] as $email) {
            $normalized = mb_strtolower(trim($email));

            if ($normalized !== '' && $normalized !== $mailbox) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Dispatch des echeances `pending` arrivees a maturite (queue
     * `communication`) — les garde-fous sont re-evalues dans le job.
     */
    private function dispatchDue(string $companyId): int
    {
        $due = CommunicationFollowUp::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', CommunicationFollowUp::STATUS_PENDING)
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->pluck('id');

        foreach ($due as $id) {
            SendCommunicationFollowUpJob::dispatch($companyId, (string) $id);
        }

        return $due->count();
    }
}
