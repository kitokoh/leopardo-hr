<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\AI\Exceptions\TokenBudgetExceededException;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Domain\Models\CommunicationReplyLog;
use App\Modules\Communication\Domain\Models\CommunicationReplyPolicy;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Orchestrateur des reponses assistees (BC-29 COMMUNICATION, R5 #7690 —
 * spec §3.5) : applique la POLITIQUE choisie par le proprietaire de la
 * boite (off/draft/confirm/auto) a un message entrant classifie (R3).
 *
 * - `off` (ou aucune politique) : rien — defaut partout ;
 * - `draft`   : brouillon genere par l'IA depose DANS LE GMAIL de
 *               l'utilisateur (scope gmail.compose requis) ;
 * - `confirm` : la proposition entre dans la file Pending durable
 *               (`communication_pending_replies`) — AUCUN envoi sans
 *               action humaine explicite (endpoint approve, policy
 *               proprietaire) ;
 * - `auto`    : envoi direct SOUS GARDE-FOUS R4 (`CommunicationReplyGuard` :
 *               opt-out, consentement CRM, quiet hours, plafond journalier)
 *               — un verdict temporel laisse la proposition en file de
 *               confirmation humaine, jamais de rejeu automatique.
 *
 * #8023 — le mode `auto` est EXCLU du chemin READ-TOOL : l'IntentEngine
 * appelle `prepare($message, allowAutoSend: false)`, qui retrograde `auto`
 * en `confirm`. Un tool call est decide par le LLM dans la boucle de
 * l'Orchestrator, sans validation humaine prealable : il ne doit jamais
 * produire d'envoi Gmail SYNCHRONE et irreversible. Le chemin canonique
 * (job `PrepareCommunicationReplyJob`) conserve le mode `auto` opt-in.
 *
 * Defense en profondeur : une politique `auto` sur une categorie bloquee
 * (finance/RH/juridique — liste en dur) est retrogradee en `confirm` meme
 * si une ligne invalide existait en base (l'API la refuse deja en 422).
 *
 * Idempotence : UNIQUE (company, message) — un message entrant ne produit
 * qu'UNE proposition, meme si la classification est rejouee (`force`).
 */
class CommunicationReplyService
{
    public function __construct(
        private readonly EmailReplyDraftService $drafts,
        private readonly CommunicationReplyGuard $guard,
        private readonly GoogleGmailReplySender $sender,
    ) {}

    /**
     * Prepare (et selon la politique, execute) la reponse assistee d'UN
     * message entrant classifie. No-op silencieux si le message n'est pas
     * eligible (sortant, auto-repondeur, liste, non classifie, politique
     * off, proposition deja existante).
     *
     * `$allowAutoSend = false` (chemin READ-TOOL, #8023 — outil
     * `email_reply_draft` de l'IntentEngine) retrograde une politique `auto`
     * en `confirm` : un tool call est decide par le LLM DANS la boucle de
     * l'Orchestrator, sans validation humaine prealable — il ne doit donc pas
     * declencher l'envoi Gmail SYNCHRONE et irreversible de `handleAuto()`
     * (`$this->sender->send()`) ; la proposition generee reste en file Pending
     * (ZERO effet externe, le brouillon est toujours renvoye a l'agent).
     * Defaut `true` : le chemin canonique (job `PrepareCommunicationReplyJob`,
     * endpoints) garde le mode `auto` opt-in intact.
     *
     * @param  bool  $allowAutoSend  false = read-tool : `auto` ramene a `confirm`
     *
     * @throws GmailRateLimitedException quota Gmail (backoff job)
     * @throws GmailSyncAuthException token mort (integration marquee error)
     */
    public function prepare(CommunicationMessage $message, bool $allowAutoSend = true): void
    {
        if ($message->classification_status !== CommunicationMessage::CLASSIFICATION_CLASSIFIED
            || $message->ai_category === null) {
            return;
        }

        // Jamais de reponse generee a un automate ou une liste (RFC 3834 /
        // List-Id, drapeaux poses par la sync R2 — meme prudence que R4).
        if ($message->is_auto_reply || $message->is_list_message) {
            return;
        }

        /** @var CommunicationIntegration|null $integration */
        $integration = $message->integration()->withoutGlobalScopes()->first();

        if ($integration === null || ! $integration->isActive()) {
            return;
        }

        $fromEmail = mb_strtolower(trim((string) $message->from_email));

        // Seuls les messages ENTRANTS recoivent une proposition de reponse.
        if ($fromEmail === '' || ! str_contains($fromEmail, '@')
            || $fromEmail === mb_strtolower((string) $integration->email)) {
            return;
        }

        $mode = $this->resolveMode($integration, $message->ai_category, $allowAutoSend);

        if ($mode === null) {
            return;
        }

        // Deduplication applicative (l'UNIQUE en base reste l'arbitre).
        $exists = CommunicationPendingReply::query()
            ->withoutGlobalScopes()
            ->where('company_id', $message->company_id)
            ->where('message_id', $message->id)
            ->exists();

        if ($exists) {
            return;
        }

        $reply = new CommunicationPendingReply;
        $reply->forceFill([
            'company_id' => $message->company_id,
            'integration_id' => $message->integration_id,
            'thread_id' => $message->thread_id,
            'message_id' => $message->id,
            'category_key' => $message->ai_category,
            'mode' => $mode,
            'to_email' => $fromEmail,
            'status' => CommunicationPendingReply::STATUS_PENDING,
        ]);

        try {
            $draft = $this->drafts->draft($message);
        } catch (TokenBudgetExceededException) {
            $this->persistFailed($reply, 'token_budget_exceeded');

            return;
        }

        if ($draft === null) {
            $this->persistFailed($reply, 'draft_generation_failed');

            return;
        }

        $reply->forceFill([
            'subject' => $draft['subject'],
            'body' => $draft['body'],
            'ai_language' => $draft['language'],
            'ai_confidence' => $draft['confidence'],
        ]);

        if (! $this->persist($reply)) {
            return; // Course perdue sur l'UNIQUE : une proposition existe deja.
        }

        match ($mode) {
            CommunicationPendingReply::MODE_DRAFT => $this->handleDraft($reply, $integration),
            CommunicationPendingReply::MODE_AUTO => $this->handleAuto($reply),
            default => CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_PROPOSED),
        };
    }

    /**
     * Politique effective de la boite pour la categorie — null = off.
     * `auto` sur une categorie bloquee est retrograde en `confirm`
     * (defense en profondeur, la liste est bloquee EN DUR). `auto` est
     * egalement retrograde en `confirm` quand l'appelant interdit l'envoi
     * synchrone (`$allowAutoSend = false`, chemin read-tool #8023).
     */
    private function resolveMode(
        CommunicationIntegration $integration,
        string $categoryKey,
        bool $allowAutoSend = true,
    ): ?string {
        /** @var CommunicationReplyPolicy|null $policy */
        $policy = CommunicationReplyPolicy::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->where('category_key', $categoryKey)
            ->first();

        if ($policy === null || $policy->policy === CommunicationReplyPolicy::POLICY_OFF) {
            return null;
        }

        if ($policy->policy === CommunicationReplyPolicy::POLICY_AUTO
            && (! $allowAutoSend || CommunicationReplyPolicy::isAutoBlockedCategory($categoryKey))) {
            return CommunicationPendingReply::MODE_CONFIRM;
        }

        return $policy->policy;
    }

    /**
     * Mode `draft` : depot du brouillon dans le Gmail du proprietaire.
     */
    private function handleDraft(CommunicationPendingReply $reply, CommunicationIntegration $integration): void
    {
        if (! $integration->hasComposeScope()) {
            $reply->forceFill([
                'status' => CommunicationPendingReply::STATUS_SKIPPED,
                'skip_reason' => 'missing_compose_scope',
            ])->save();
            CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_SKIPPED, 'missing_compose_scope');

            return;
        }

        $draftId = $this->sender->createDraft($reply);

        if ($draftId === null) {
            $reply->forceFill([
                'status' => CommunicationPendingReply::STATUS_FAILED,
                'skip_reason' => 'gmail_draft_failed',
            ])->save();
            CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_FAILED, 'gmail_draft_failed');

            return;
        }

        $reply->forceFill([
            'status' => CommunicationPendingReply::STATUS_DRAFTED,
            'gmail_draft_id' => $draftId,
        ])->save();
        CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_DRAFT_CREATED);
    }

    /**
     * Mode `auto` : envoi direct sous garde-fous R4. Verdict temporel
     * (quiet hours, plafond) -> la proposition RESTE `pending` et attend
     * une validation humaine (jamais de rejeu automatique).
     */
    private function handleAuto(CommunicationPendingReply $reply): void
    {
        ['verdict' => $verdict, 'reason' => $reason] = $this->guard->evaluate($reply);

        if ($verdict === CommunicationReplyGuard::VERDICT_SKIP) {
            $reply->forceFill([
                'status' => CommunicationPendingReply::STATUS_SKIPPED,
                'skip_reason' => $reason,
            ])->save();
            CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_SKIPPED, $reason);

            return;
        }

        if ($verdict === CommunicationReplyGuard::VERDICT_DEFER) {
            $reply->forceFill(['skip_reason' => $reason])->save();
            CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_DEFERRED, $reason);

            return;
        }

        $sentId = $this->sender->send($reply);

        if ($sentId === null) {
            $reply->forceFill([
                'status' => CommunicationPendingReply::STATUS_FAILED,
                'skip_reason' => 'gmail_send_failed',
            ])->save();
            CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_FAILED, 'gmail_send_failed');

            return;
        }

        $reply->forceFill([
            'status' => CommunicationPendingReply::STATUS_SENT,
            'sent_gmail_message_id' => $sentId,
            'sent_at' => now(),
        ])->save();
        CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_AUTO_SENT);
    }

    private function persistFailed(CommunicationPendingReply $reply, string $reason): void
    {
        $reply->forceFill([
            'status' => CommunicationPendingReply::STATUS_FAILED,
            'skip_reason' => $reason,
        ]);

        if ($this->persist($reply)) {
            CommunicationReplyLog::record($reply, CommunicationReplyLog::ACTION_FAILED, $reason);
        }
    }

    private function persist(CommunicationPendingReply $reply): bool
    {
        try {
            $reply->save();
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
