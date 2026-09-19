<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpLog;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Modules\Communication\Domain\Models\CommunicationReplyLog;
use App\Modules\Communication\Domain\Models\CommunicationReplyPolicy;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use App\Modules\Communication\Infrastructure\Jobs\ClassifyCommunicationMessageJob;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Synchronisation Gmail INCREMENTALE d'une boite connectee (BC-29, R2 #7687
 * — spec MODULE_COMMUNICATION_EMAIL_IA.md §3.2).
 *
 * Strategie :
 * 1. `sync_history_id` present -> sync incrementale via
 *    `users.history.list?startHistoryId=` (types `messageAdded`) : seuls
 *    les fils touches depuis la derniere passe sont re-ingeres. Google
 *    repond 404 quand le historyId est trop ancien (~1 semaine) -> le
 *    curseur est efface et on retombe sur la full sync.
 * 2. Sinon (premiere connexion, curseur expire) -> FULL SYNC bornee aux
 *    {@see FULL_SYNC_MAX_THREADS} fils les plus recents (critere
 *    d'acceptation « 50 derniers fils visibles apres connexion »). Le
 *    `historyId` du profil est capture AVANT de paginer : tout evenement
 *    survenu pendant la passe sera rejoue par l'incremental suivant.
 *    `sync_page_token` persiste la pagination -> une full sync interrompue
 *    (retry, quota) reprend ou elle s'etait arretee.
 *
 * Idempotence / anti-doublons : chaque thread et message est upserte sur
 * sa cle Gmail (uniques en base) — un resync complet ne duplique rien.
 *
 * MINIMISATION (exigence issue) : seuls les headers utiles (From/To/Cc/
 * Subject/Message-ID/In-Reply-To/Date), le snippet, les labels, la partie
 * text/plain bornee (chiffree au repos par le cast du modele) et les
 * REFERENCES de pieces jointes sont conserves. Jamais de raw MIME, jamais
 * de HTML, jamais de contenu de piece jointe.
 *
 * Erreurs : 401 -> integration `error` + GmailSyncAuthException (le job
 * notifie l'utilisateur) ; 429 -> GmailRateLimitedException (backoff côté
 * job, Retry-After respecte). Les tokens passent par le service OAuth R1
 * (refresh transparent) et ne sont JAMAIS journalises.
 */
class GoogleGmailSyncService
{
    public const GMAIL_API_BASE = 'https://gmail.googleapis.com/gmail/v1/users/me';

    /**
     * Borne de la full sync V1 : les 50 fils les plus recents (acceptation
     * #7687). L'historique plus ancien n'est pas ingere (minimisation).
     */
    public const FULL_SYNC_MAX_THREADS = 50;

    /**
     * Backoff par defaut quand Google repond 429 sans header Retry-After.
     */
    public const DEFAULT_RETRY_AFTER_SECONDS = 60;

    public function __construct(private readonly GoogleGmailOAuthService $oauth) {}

    /**
     * Passe de synchronisation d'UNE boite. Leve GmailRateLimitedException
     * (backoff job) ou GmailSyncAuthException (token mort, deja marque
     * `error` par le service OAuth R1).
     */
    public function sync(CommunicationIntegration $integration): void
    {
        $accessToken = $this->oauth->ensureValidAccessToken($integration);

        if ($accessToken === null) {
            // Refresh refuse (invalid_grant…) : le service R1 a deja pose
            // status=error + last_error — on remonte pour notifier.
            throw new GmailSyncAuthException((string) ($integration->last_error ?? 'gmail_auth_failed'));
        }

        // Incremental seulement si une full sync a abouti (curseur pose) et
        // qu'aucune full sync n'est en cours de reprise (page token).
        if ($integration->sync_history_id !== null && $integration->sync_page_token === null) {
            if ($this->incrementalSync($integration, $accessToken)) {
                return;
            }

            // historyId expire (404 Gmail) : fallback full sync (exigence issue).
            Log::info('communication.gmail.history_expired_falling_back_to_full_sync', [
                'integration_id' => $integration->id,
            ]);

            $integration->forceFill(['sync_history_id' => null])->save();
        }

        $this->fullSync($integration, $accessToken);
    }

    /**
     * Purge COMPLETE des donnees synchronisees d'une boite (exigence #7687 :
     * « purge complete a la deconnexion ») — appelee par le flow de
     * revocation R1. Les curseurs de sync sont aussi effaces : une
     * reconnexion repart d'une full sync propre.
     */
    public function purge(CommunicationIntegration $integration): void
    {
        // R4 (#7689) — droit a l'effacement etendu aux relances : echeances,
        // regles (FK cascade) et journal d'audit de la boite.
        CommunicationFollowUpLog::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        // R5 (#7690) — idem pour les reponses assistees : journal d'audit,
        // file Pending (contenus generes chiffres) et politiques de la boite.
        CommunicationReplyLog::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        CommunicationPendingReply::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        CommunicationReplyPolicy::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        CommunicationFollowUpRule::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        CommunicationThread::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->delete();

        $integration->forceFill([
            'sync_history_id' => null,
            'sync_page_token' => null,
            'last_synced_at' => null,
        ])->save();
    }

    /**
     * Sync incrementale via users.history.list. Retourne false si Google
     * signale un historyId expire (404) — l'appelant retombe en full sync.
     */
    private function incrementalSync(CommunicationIntegration $integration, string $accessToken): bool
    {
        $pageToken = null;
        $latestHistoryId = $integration->sync_history_id;
        /** @var array<string, true> $threadIds */
        $threadIds = [];

        do {
            $response = $this->gmailGet($integration, $accessToken, '/history', array_filter([
                'startHistoryId' => (string) $integration->sync_history_id,
                'historyTypes' => 'messageAdded',
                'pageToken' => $pageToken,
            ]));

            if ($response->status() === 404) {
                return false;
            }

            $this->guardGmailResponse($integration, $response, 'history_list');

            /** @var list<array<string, mixed>> $history */
            $history = (array) $response->json('history', []);

            foreach ($history as $entry) {
                /** @var list<array<string, mixed>> $added */
                $added = (array) ($entry['messagesAdded'] ?? []);

                foreach ($added as $item) {
                    $threadId = $item['message']['threadId'] ?? null;

                    if (is_string($threadId) && $threadId !== '') {
                        $threadIds[$threadId] = true;
                    }
                }
            }

            if (is_string($response->json('historyId'))) {
                $latestHistoryId = (string) $response->json('historyId');
            }

            $pageToken = is_string($response->json('nextPageToken'))
                ? (string) $response->json('nextPageToken')
                : null;
        } while ($pageToken !== null);

        // Re-ingestion idempotente de chaque fil touche (upserts sur cles
        // Gmail : resync sans doublons).
        foreach (array_keys($threadIds) as $threadId) {
            $this->fetchAndIngestThread($integration, $accessToken, $threadId);
        }

        $integration->forceFill([
            'sync_history_id' => $latestHistoryId,
            'last_synced_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Full sync bornee aux 50 fils les plus recents, reprenable via
     * `sync_page_token` (persiste apres chaque page).
     */
    private function fullSync(CommunicationIntegration $integration, string $accessToken): void
    {
        // historyId capture AVANT la pagination : les evenements survenus
        // pendant la passe seront rejoues par l'incremental suivant.
        $profile = $this->gmailGet($integration, $accessToken, '/profile');
        $this->guardGmailResponse($integration, $profile, 'profile');

        $checkpointHistoryId = is_scalar($profile->json('historyId'))
            ? (string) $profile->json('historyId')
            : null;

        $pageToken = $integration->sync_page_token;
        $ingested = 0;

        do {
            $response = $this->gmailGet($integration, $accessToken, '/threads', array_filter([
                'maxResults' => (string) self::FULL_SYNC_MAX_THREADS,
                'pageToken' => $pageToken,
            ]));

            $this->guardGmailResponse($integration, $response, 'threads_list');

            /** @var list<array<string, mixed>> $threads */
            $threads = (array) $response->json('threads', []);

            foreach ($threads as $summary) {
                if ($ingested >= self::FULL_SYNC_MAX_THREADS) {
                    break;
                }

                $threadId = $summary['id'] ?? null;

                if (is_string($threadId) && $threadId !== '') {
                    $this->fetchAndIngestThread($integration, $accessToken, $threadId);
                    $ingested++;
                }
            }

            $pageToken = is_string($response->json('nextPageToken'))
                ? (string) $response->json('nextPageToken')
                : null;

            if ($ingested >= self::FULL_SYNC_MAX_THREADS) {
                $pageToken = null;
            }

            // Reprise possible apres interruption (retry job, quota).
            $integration->forceFill(['sync_page_token' => $pageToken])->save();
        } while ($pageToken !== null);

        $integration->forceFill([
            'sync_history_id' => $checkpointHistoryId,
            'sync_page_token' => null,
            'last_synced_at' => now(),
        ])->save();
    }

    private function fetchAndIngestThread(
        CommunicationIntegration $integration,
        string $accessToken,
        string $gmailThreadId,
    ): void {
        $response = $this->gmailGet($integration, $accessToken, '/threads/'.$gmailThreadId, [
            'format' => 'full',
        ]);

        if ($response->status() === 404) {
            // Fil supprime cote Gmail entre la liste et le fetch : ignore.
            return;
        }

        $this->guardGmailResponse($integration, $response, 'thread_get');

        /** @var array<string, mixed> $payload */
        $payload = (array) $response->json();

        $this->ingestThread($integration, $payload);
    }

    /**
     * Upsert d'un fil + de ses messages (idempotent — cles Gmail uniques).
     *
     * @param  array<string, mixed>  $payload
     */
    private function ingestThread(CommunicationIntegration $integration, array $payload): void
    {
        $gmailThreadId = $payload['id'] ?? null;

        if (! is_string($gmailThreadId) || $gmailThreadId === '') {
            return;
        }

        /** @var list<array<string, mixed>> $messages */
        $messages = (array) ($payload['messages'] ?? []);

        /** @var CommunicationThread $thread */
        $thread = CommunicationThread::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->where('gmail_thread_id', $gmailThreadId)
            ->firstOrNew([]);

        if (! $thread->exists) {
            $thread->forceFill([
                'company_id' => $integration->company_id,
                'integration_id' => $integration->id,
                'gmail_thread_id' => $gmailThreadId,
            ]);

            // Sauve immediatement : les messages ont besoin de l'uuid du fil.
            $thread->save();
        }

        $subject = null;
        $lastSnippet = null;
        $lastMessageAt = null;

        foreach ($messages as $message) {
            $ingested = $this->ingestMessage($integration, $thread, $message);

            if ($ingested === null) {
                continue;
            }

            $subject ??= $ingested->subject;
            $lastSnippet = $ingested->snippet ?? $lastSnippet;

            if ($ingested->sent_at !== null
                && ($lastMessageAt === null || $ingested->sent_at->gt($lastMessageAt))) {
                $lastMessageAt = $ingested->sent_at;
            }
        }

        $thread->forceFill([
            'subject' => $subject !== null ? mb_substr($subject, 0, 998) : $thread->subject,
            'snippet' => $lastSnippet !== null ? mb_substr($lastSnippet, 0, 500) : $thread->snippet,
            'last_message_at' => $lastMessageAt ?? $thread->last_message_at,
        ])->save();

        $thread->forceFill([
            'message_count' => CommunicationMessage::query()
                ->withoutGlobalScopes()
                ->where('thread_id', $thread->id)
                ->count(),
        ])->save();
    }

    /**
     * Upsert d'UN message, en ne conservant que le strict necessaire
     * (minimisation #7687).
     *
     * @param  array<string, mixed>  $payload
     */
    private function ingestMessage(
        CommunicationIntegration $integration,
        CommunicationThread $thread,
        array $payload,
    ): ?CommunicationMessage {
        $gmailMessageId = $payload['id'] ?? null;

        if (! is_string($gmailMessageId) || $gmailMessageId === '') {
            return null;
        }

        /** @var array<string, mixed> $part */
        $part = (array) ($payload['payload'] ?? []);
        $headers = $this->headerMap($part);

        $sentAt = null;

        if (is_scalar($payload['internalDate'] ?? null)) {
            $milliseconds = (int) $payload['internalDate'];

            if ($milliseconds > 0) {
                $sentAt = Carbon::createFromTimestampMs($milliseconds);
            }
        }

        /** @var CommunicationMessage $message */
        $message = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $integration->company_id)
            ->where('integration_id', $integration->id)
            ->where('gmail_message_id', $gmailMessageId)
            ->firstOrNew([]);

        if (! $message->exists) {
            $message->forceFill([
                'company_id' => $integration->company_id,
                'integration_id' => $integration->id,
                'gmail_message_id' => $gmailMessageId,
            ]);
        }

        $body = $this->extractPlainTextBody($part);
        $subject = $headers['subject'] ?? null;
        $snippet = is_string($payload['snippet'] ?? null) ? (string) $payload['snippet'] : null;

        $message->forceFill([
            'thread_id' => $thread->id,
            'internet_message_id' => isset($headers['message-id']) ? mb_substr($headers['message-id'], 0, 998) : null,
            'in_reply_to' => isset($headers['in-reply-to']) ? mb_substr($headers['in-reply-to'], 0, 998) : null,
            'from_email' => isset($headers['from']) ? ($this->extractEmails($headers['from'])[0] ?? null) : null,
            'to_emails' => isset($headers['to']) ? $this->extractEmails($headers['to']) : null,
            'cc_emails' => isset($headers['cc']) ? $this->extractEmails($headers['cc']) : null,
            'subject' => $subject !== null ? mb_substr($subject, 0, 998) : null,
            'snippet' => $snippet !== null ? mb_substr($snippet, 0, 500) : null,
            'body' => $body,
            'labels' => array_values(array_filter(
                (array) ($payload['labelIds'] ?? []),
                'is_string'
            )),
            'attachment_refs' => $this->extractAttachmentRefs($part),
            'sent_at' => $sentAt,
            // R4 (#7689) — garde-fous relances : seuls des BOOLEENS sont
            // persistes, les headers Auto-Submitted / List-Id eux-memes ne
            // sont jamais stockes (minimisation R2 conservee).
            'is_auto_reply' => isset($headers['auto-submitted'])
                && mb_strtolower(trim($headers['auto-submitted'])) !== 'no',
            'is_list_message' => isset($headers['list-id']),
        ]);

        $message->save();

        // R3 (#7688) — « messages classés à la sync » : chaque message
        // nouvellement ingéré (ou re-syncé avant classification) part en
        // classification IA sur la queue `communication`. Idempotent : un
        // message déjà classifié n'est pas re-dispatché. NB : sur une ligne
        // fraîchement insérée, l'attribut vaut null en mémoire (défaut
        // `pending` posé par la base) — null est donc traité comme pending.
        $status = $message->classification_status ?? CommunicationMessage::CLASSIFICATION_PENDING;

        if ($status === CommunicationMessage::CLASSIFICATION_PENDING) {
            ClassifyCommunicationMessageJob::dispatch(
                (string) $integration->company_id,
                (string) $message->id,
            );
        }

        return $message;
    }

    /**
     * GET Gmail authentifie. 401 -> integration `error` +
     * GmailSyncAuthException ; 429 -> GmailRateLimitedException.
     *
     * @param  array<string, string>  $query
     */
    private function gmailGet(
        CommunicationIntegration $integration,
        string $accessToken,
        string $path,
        array $query = [],
    ): Response {
        return Http::withToken($accessToken)->get(self::GMAIL_API_BASE.$path, $query);
    }

    /**
     * Erreurs transverses des reponses Gmail — codes machine en logs
     * uniquement, JAMAIS de payload Google (minimisation, pas de PII).
     */
    private function guardGmailResponse(
        CommunicationIntegration $integration,
        Response $response,
        string $operation,
    ): void {
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
                $retryAfter > 0 ? $retryAfter : self::DEFAULT_RETRY_AFTER_SECONDS
            );
        }

        if ($response->failed()) {
            Log::warning('communication.gmail.sync_request_failed', [
                'integration_id' => $integration->id,
                'operation' => $operation,
                'status' => $response->status(),
            ]);

            $response->throw();
        }
    }

    /**
     * Headers RFC 5322 UTILES uniquement (minimisation) — cles en minuscules.
     *
     * @param  array<string, mixed>  $part
     * @return array<string, string>
     */
    private function headerMap(array $part): array
    {
        $wanted = ['from', 'to', 'cc', 'subject', 'message-id', 'in-reply-to', 'auto-submitted', 'list-id'];
        $map = [];

        /** @var list<array<string, mixed>> $headers */
        $headers = (array) ($part['headers'] ?? []);

        foreach ($headers as $header) {
            $name = is_string($header['name'] ?? null) ? mb_strtolower($header['name']) : null;
            $value = $header['value'] ?? null;

            if ($name !== null && is_string($value) && in_array($name, $wanted, true)) {
                $map[$name] = $value;
            }
        }

        return $map;
    }

    /**
     * Adresses email seules a partir d'un header From/To/Cc (les display
     * names ne sont pas conserves — minimisation).
     *
     * @return list<string>
     */
    private function extractEmails(string $header): array
    {
        preg_match_all('/[A-Za-z0-9._%+\'-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $header, $matches);

        return array_values(array_unique(array_map(
            static fn (string $email): string => mb_strtolower($email),
            $matches[0]
        )));
    }

    /**
     * Partie text/plain du message, decodee (base64url Gmail) et bornee.
     * Jamais de HTML, jamais de raw MIME.
     *
     * @param  array<string, mixed>  $part
     */
    private function extractPlainTextBody(array $part): ?string
    {
        $mimeType = $part['mimeType'] ?? null;
        $data = $part['body']['data'] ?? null;

        if (is_string($mimeType) && str_starts_with($mimeType, 'text/plain') && is_string($data)) {
            $decoded = base64_decode(strtr($data, '-_', '+/'), true);

            if (is_string($decoded) && $decoded !== '') {
                return mb_substr($decoded, 0, CommunicationMessage::BODY_MAX_BYTES);
            }
        }

        /** @var list<array<string, mixed>> $children */
        $children = (array) ($part['parts'] ?? []);

        foreach ($children as $child) {
            $body = $this->extractPlainTextBody((array) $child);

            if ($body !== null) {
                return $body;
            }
        }

        return null;
    }

    /**
     * REFERENCES de pieces jointes uniquement (attachmentId, filename,
     * mimeType, size) — le contenu n'est JAMAIS telecharge ni stocke
     * (exigence issue : « pieces jointes non stockees, reference Gmail »).
     *
     * @param  array<string, mixed>  $part
     * @return list<array<string, mixed>>
     */
    private function extractAttachmentRefs(array $part): array
    {
        $refs = [];

        $filename = $part['filename'] ?? null;
        $attachmentId = $part['body']['attachmentId'] ?? null;

        if (is_string($filename) && $filename !== '' && is_string($attachmentId) && $attachmentId !== '') {
            $refs[] = [
                'attachment_id' => $attachmentId,
                'filename' => mb_substr($filename, 0, 255),
                'mime_type' => is_string($part['mimeType'] ?? null) ? (string) $part['mimeType'] : null,
                'size' => is_scalar($part['body']['size'] ?? null) ? (int) $part['body']['size'] : null,
            ];
        }

        /** @var list<array<string, mixed>> $children */
        $children = (array) ($part['parts'] ?? []);

        foreach ($children as $child) {
            $refs = array_merge($refs, $this->extractAttachmentRefs((array) $child));
        }

        return $refs;
    }
}
