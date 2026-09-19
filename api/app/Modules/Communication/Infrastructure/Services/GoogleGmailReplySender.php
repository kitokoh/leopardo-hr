<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi / depot en brouillon d'une reponse assistee via le Gmail DU
 * PROPRIETAIRE de la boite (BC-29 COMMUNICATION, R5 #7690 — spec §3.5),
 * pattern du sender de relances R4 (`GoogleGmailFollowUpSender`).
 *
 * - Tokens : via le service OAuth R1 (`ensureValidAccessToken`, refresh
 *   transparent, chiffres au repos) — JAMAIS journalises.
 * - Threading : la reponse part DANS LE MEME FIL Gmail (`threadId`) avec
 *   les references RFC 5322 (`In-Reply-To`/`References` sur le Message-ID
 *   du message ENTRANT auquel on repond, conserve par la sync R2).
 * - Contenu : sujet/corps de la file Pending (generes par l'IA, edites et
 *   VALIDES par l'humain en mode confirm) — text/plain uniquement.
 * - Erreurs : 401 -> integration `error` + GmailSyncAuthException (pattern
 *   R2/R4) ; 429 -> GmailRateLimitedException (Retry-After) ; autre echec
 *   -> null (l'appelant marque la proposition `failed`, code machine).
 */
class GoogleGmailReplySender
{
    public const SEND_ENDPOINT = GoogleGmailSyncService::GMAIL_API_BASE.'/messages/send';

    public const DRAFTS_ENDPOINT = GoogleGmailSyncService::GMAIL_API_BASE.'/drafts';

    public function __construct(private readonly GoogleGmailOAuthService $oauth) {}

    /**
     * Envoie la reponse. Retourne l'id Gmail du message envoye, ou null en
     * cas d'echec definitif (deja journalise, code machine).
     *
     * @throws GmailRateLimitedException quota Gmail (Retry-After)
     * @throws GmailSyncAuthException token mort (integration marquee error)
     */
    public function send(CommunicationPendingReply $reply): ?string
    {
        $response = $this->post($reply, self::SEND_ENDPOINT, fn (array $payload): array => $payload);

        if ($response === null) {
            return null;
        }

        $id = $response->json('id');

        if ($response->failed() || ! is_string($id)) {
            Log::warning('communication.reply.send_failed', [
                'pending_reply_id' => $reply->id,
                'integration_id' => $reply->integration_id,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $id;
    }

    /**
     * Depose la reponse en BROUILLON dans le Gmail du proprietaire (mode
     * `draft`). Retourne l'id du brouillon, ou null en cas d'echec.
     *
     * @throws GmailRateLimitedException
     * @throws GmailSyncAuthException
     */
    public function createDraft(CommunicationPendingReply $reply): ?string
    {
        $response = $this->post(
            $reply,
            self::DRAFTS_ENDPOINT,
            static fn (array $payload): array => ['message' => $payload],
        );

        if ($response === null) {
            return null;
        }

        $id = $response->json('id');

        if ($response->failed() || ! is_string($id)) {
            Log::warning('communication.reply.draft_failed', [
                'pending_reply_id' => $reply->id,
                'integration_id' => $reply->integration_id,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $id;
    }

    /**
     * POST Gmail commun (send / drafts) avec la gestion d'erreurs R2/R4.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $wrap
     *
     * @throws GmailRateLimitedException
     * @throws GmailSyncAuthException
     */
    private function post(CommunicationPendingReply $reply, string $endpoint, callable $wrap): ?Response
    {
        /** @var CommunicationIntegration|null $integration */
        $integration = $reply->integration;

        if ($integration === null || $integration->email === null) {
            return null;
        }

        $accessToken = $this->oauth->ensureValidAccessToken($integration);

        if ($accessToken === null) {
            throw new GmailSyncAuthException((string) ($integration->last_error ?? 'gmail_auth_failed'));
        }

        $raw = $this->buildMime($integration, $reply);

        $payload = array_filter([
            'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            'threadId' => $reply->thread?->gmail_thread_id,
        ]);

        $response = Http::withToken($accessToken)->post($endpoint, $wrap($payload));

        if ($response->status() === 401) {
            $integration->forceFill([
                'status' => CommunicationIntegration::STATUS_ERROR,
                'access_token' => null,
                'expires_at' => null,
                'last_error' => 'gmail_unauthorized',
            ])->save();

            throw new GmailSyncAuthException('gmail_unauthorized');
        }

        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('Retry-After');

            throw new GmailRateLimitedException(
                $retryAfter > 0 ? $retryAfter : GoogleGmailSyncService::DEFAULT_RETRY_AFTER_SECONDS
            );
        }

        return $response;
    }

    /**
     * Message RFC 2822 minimal (text/plain) dans le fil d'origine, en
     * reponse au message ENTRANT ancre de la proposition.
     */
    private function buildMime(CommunicationIntegration $integration, CommunicationPendingReply $reply): string
    {
        /** @var CommunicationMessage|null $anchor */
        $anchor = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $reply->company_id)
            ->find($reply->message_id);

        $subject = (string) ($reply->subject ?? '');

        if ($subject === '') {
            $original = $anchor !== null ? (string) ($anchor->subject ?? '') : '';
            $subject = $original !== ''
                ? (str_starts_with(mb_strtolower($original), 're:') ? $original : 'Re: '.$original)
                : 'Re:';
        }

        $headers = [
            'From: '.$integration->email,
            'To: '.$reply->to_email,
            'Subject: '.$this->encodeHeader($subject),
        ];

        if ($anchor?->internet_message_id !== null) {
            $headers[] = 'In-Reply-To: '.$anchor->internet_message_id;
            $headers[] = 'References: '.$anchor->internet_message_id;
        }

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';

        return implode("\r\n", $headers)
            ."\r\n\r\n"
            .chunk_split(base64_encode((string) $reply->body), 76, "\r\n");
    }

    /**
     * Encode un header non-ASCII (RFC 2047) — les sujets accentues passent
     * tels quels chez Gmail.
     */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }
}
