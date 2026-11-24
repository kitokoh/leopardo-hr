<?php

declare(strict_types=1);

namespace App\AI\Support;

use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Infrastructure\Jobs\PrepareCommunicationReplyJob;
use App\Modules\Communication\Infrastructure\Services\CommunicationReplyService;
use App\Modules\Communication\Infrastructure\Services\EmailClassificationService;

/**
 * Handlers de lecture Communication de l'assistant — extraits de `IntentEngine`
 * par #9887.
 *
 * ## La regle qui gouverne ce fichier
 *
 * Ces deux outils sont les seuls read-tools qui declenchent un EFFET EXTERNE
 * (un brouillon Gmail, une entree dans la file de reponses). Ils sont donc
 * isoles : une revue de securite doit pouvoir verifier d'un coup d'oeil que
 * `email_reply_draft` ne peut pas ENVOYER.
 *
 * #8023 — le mode `auto` (envoi direct) est retrograde en `confirm` sur ce
 * chemin (`allowAutoSend: false`). Un tool call est decide par le LLM dans la
 * boucle de l'Orchestrator, sans validation humaine prealable : il ne doit pas
 * y produire d'envoi Gmail synchrone et irreversible. Le chemin canonique
 * (job `PrepareCommunicationReplyJob`) conserve le mode `auto` opt-in.
 */
final class CommunicationReadHandlers
{
    /**
     * R3 Communication (#7688) — handler du read-tool `email_classify` :
     * classification IA d'un message synchronise, PARITE avec le pipeline
     * canonique (`ClassifyCommunicationMessageJob`) — service
     * `EmailClassificationService` (sortie validee contre la taxonomie du
     * tenant, contenu traite comme donnee hostile, budget fail-closed) puis,
     * si le message est classe, chainage idempotent de la preparation de
     * reponse R5 (le job dedupplique via l'UNIQUE company+message).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function classifyCommunicationMessage(string $companyId, array $args): array
    {
        $message = $this->findCommunicationMessage($companyId, $args['message_id'] ?? null);

        if (! $message instanceof CommunicationMessage) {
            return ['error' => 'Message not found'];
        }

        $force = filter_var($args['force'] ?? false, FILTER_VALIDATE_BOOL);

        app(EmailClassificationService::class)->classify($message, $force);
        $message->refresh();

        // Parite avec le job canonique : une classification reussie alimente
        // le pipeline des reponses assistees R5 (#7690) — idempotent (UNIQUE
        // company+message dans la file Pending, aucun doublon au rejeu).
        if ($message->classification_status === CommunicationMessage::CLASSIFICATION_CLASSIFIED) {
            PrepareCommunicationReplyJob::dispatch($companyId, (string) $message->id);
        }

        return [
            'message_id' => $message->id,
            'classification_status' => $message->classification_status,
            'category' => $message->ai_category,
            'language' => $message->ai_language,
            'sentiment' => $message->ai_sentiment,
            'action' => $message->ai_action,
            'confidence' => $message->ai_confidence,
            'error' => $message->classification_error,
        ];
    }

    /**
     * R5 Communication (#7690) — handler du read-tool `email_reply_draft` :
     * brouillon de reponse a un email entrant classe. Chemin canonique
     * `CommunicationReplyService::prepare()` : la POLITIQUE de la boite
     * (off/draft/confirm/auto) decide — le texte genere entre dans la file
     * Pending (validation humaine en confirm), et le mode `draft` depose en
     * plus un brouillon REVERSIBLE dans le Gmail du proprietaire
     * (`gmail.compose`, jamais d'envoi).
     *
     * #8023 — le mode `auto` (envoi direct) est EXCLU de ce chemin read-tool :
     * `prepare(..., allowAutoSend: false)` retrograde `auto` en `confirm`. Un
     * tool call est decide par le LLM DANS la boucle de l'Orchestrator, sans
     * validation humaine prealable : il ne doit pas y produire d'ENVOI Gmail
     * synchrone et irreversible (`CommunicationReplyService::handleAuto()` →
     * `GoogleGmailReplySender::send()`), contrairement au chemin canonique
     * (job `PrepareCommunicationReplyJob`) qui conserve le mode `auto` opt-in.
     * Le brouillon genere est malgre tout renvoye par l'outil (il alimente la
     * file Pending) ; l'envoi eventuel reste une decision humaine explicite
     * (endpoint approve, proprietaire de la boite).
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function draftCommunicationReply(string $companyId, array $args): array
    {
        $message = $this->findCommunicationMessage($companyId, $args['message_id'] ?? null);

        if (! $message instanceof CommunicationMessage) {
            return ['error' => 'Message not found'];
        }

        if ($message->classification_status !== CommunicationMessage::CLASSIFICATION_CLASSIFIED) {
            return [
                'error' => 'message_not_classified',
                'message' => "Classifiez d'abord le message (outil email_classify) avant de demander un brouillon.",
            ];
        }

        // #8023 — `allowAutoSend: false` : le tool force le mode non-envoyant
        // (auto retrograde en confirm) — aucune sortie Gmail synchrone ici.
        app(CommunicationReplyService::class)->prepare($message, allowAutoSend: false);

        /** @var CommunicationPendingReply|null $reply */
        $reply = CommunicationPendingReply::query()
            ->where('company_id', $companyId)
            ->where('message_id', $message->id)
            ->first();

        if ($reply === null) {
            // Politique off (ou message ineligible : sortant, auto-repondeur,
            // liste) — aucune proposition creee : c'est le comportement voulu.
            return [
                'message_id' => $message->id,
                'status' => 'no_reply_prepared',
                'message' => "Aucun brouillon prepare : la politique de reponse de la boite est inactive pour cette categorie (ou le message n'est pas eligible).",
            ];
        }

        return [
            'message_id' => $message->id,
            'pending_reply_id' => $reply->id,
            'status' => $reply->status,
            'mode' => $reply->mode,
            'subject' => $reply->subject,
            'body' => $reply->body,
            'confidence' => $reply->ai_confidence,
        ];
    }

    /**
     * Message Communication du tenant courant (cle UUID) — defense en
     * profondeur : filtre `company_id` explicite, le pipeline IA resout le
     * tenant depuis la session, jamais depuis les arguments du LLM.
     */
    public function findCommunicationMessage(string $companyId, mixed $messageId): ?CommunicationMessage
    {
        if (! is_string($messageId) || $messageId === '') {
            return null;
        }

        /** @var CommunicationMessage|null $message */
        $message = CommunicationMessage::query()
            ->where('company_id', $companyId)
            ->find($messageId);

        return $message;
    }
}
