<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Core\Mail\EmailTemplateResolver;
use App\Modules\Communication\Domain\Exceptions\GmailRateLimitedException;
use App\Modules\Communication\Domain\Exceptions\GmailSyncAuthException;
use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Modules\Communication\Domain\Models\CommunicationThread;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi d'une relance via le Gmail DU PROPRIETAIRE de la boite (BC-29
 * COMMUNICATION, R4 #7689 — spec §3.4).
 *
 * - Tokens : via le service OAuth R1 (`ensureValidAccessToken`, refresh
 *   transparent, chiffres au repos) — JAMAIS journalises.
 * - Threading : la relance part DANS LE MEME FIL Gmail (`threadId`) avec
 *   les references RFC 5322 (`In-Reply-To`/`References` sur le Message-ID
 *   du message sortant ancre, conserve par la sync R2 exactement pour ca).
 * - Contenu : gabarit `EmailTemplateRegistry` (#7347) resolu dans la locale
 *   du proprietaire (surcharge admin possible par locale) — variables
 *   FERMEES (:subject, :name, :brand), jamais de texte libre.
 * - Erreurs : 401 -> integration `error` + GmailSyncAuthException (pattern
 *   sync R2) ; 429 -> GmailRateLimitedException (backoff job, Retry-After) ;
 *   autre echec -> null (le job marque l'echeance `failed`, code machine).
 *
 * @phpstan-type SentMessage array{gmail_message_id: string}
 */
class GoogleGmailFollowUpSender
{
    public const SEND_ENDPOINT = GoogleGmailSyncService::GMAIL_API_BASE.'/messages/send';

    public function __construct(
        private readonly GoogleGmailOAuthService $oauth,
        private readonly EmailTemplateResolver $templates,
    ) {}

    /**
     * Envoie la relance. Retourne l'id Gmail du message envoye, ou null en
     * cas d'echec definitif (deja journalise, code machine).
     *
     * @throws GmailRateLimitedException quota Gmail (backoff job)
     * @throws GmailSyncAuthException token mort (integration marquee error)
     */
    public function send(CommunicationFollowUp $followUp, string $templateKey): ?string
    {
        /** @var CommunicationIntegration|null $integration */
        $integration = $followUp->integration;

        if ($integration === null || $integration->email === null) {
            return null;
        }

        $accessToken = $this->oauth->ensureValidAccessToken($integration);

        if ($accessToken === null) {
            throw new GmailSyncAuthException((string) ($integration->last_error ?? 'gmail_auth_failed'));
        }

        /** @var CommunicationThread|null $thread */
        $thread = $followUp->thread;

        /** @var CommunicationMessage|null $anchor */
        $anchor = $followUp->message_id !== null
            ? CommunicationMessage::query()
                ->withoutGlobalScopes()
                ->where('company_id', $followUp->company_id)
                ->find($followUp->message_id)
            : null;

        $raw = $this->buildMime($integration, $followUp, $thread, $anchor, $templateKey);

        $response = Http::withToken($accessToken)->post(self::SEND_ENDPOINT, array_filter([
            'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
            'threadId' => $thread?->gmail_thread_id,
        ]));

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

        if ($response->failed() || ! is_string($response->json('id'))) {
            // Code machine seulement — jamais de payload Google en logs.
            Log::warning('communication.follow_up.send_failed', [
                'follow_up_id' => $followUp->id,
                'integration_id' => $integration->id,
                'status' => $response->status(),
            ]);

            return null;
        }

        return (string) $response->json('id');
    }

    /**
     * Message RFC 2822 minimal (text/plain) dans le fil d'origine.
     */
    private function buildMime(
        CommunicationIntegration $integration,
        CommunicationFollowUp $followUp,
        ?CommunicationThread $thread,
        ?CommunicationMessage $anchor,
        string $templateKey,
    ): string {
        $employee = $integration->employee;
        // Pas de preference de locale par employe dans le modele : la
        // relance part dans la locale de l'application (surcharge admin du
        // gabarit possible par locale via EmailTemplateRegistry #7347).
        $locale = (string) config('app.locale');

        $originalSubject = $anchor->subject ?? $thread->subject ?? '';

        $senderName = trim(sprintf(
            '%s %s',
            (string) ($employee->first_name ?? ''),
            (string) ($employee->last_name ?? ''),
        ));

        $template = $this->templates->resolve($templateKey, $locale, [
            ':subject' => $originalSubject,
            ':name' => $senderName !== '' ? $senderName : (string) $integration->email,
            ':brand' => (string) config('app.name'),
        ]);

        $subject = $originalSubject !== ''
            ? (str_starts_with(mb_strtolower($originalSubject), 're:') ? $originalSubject : 'Re: '.$originalSubject)
            : $template->subject;

        $headers = [
            'From: '.$integration->email,
            'To: '.$followUp->contact_email,
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
            .chunk_split(base64_encode($template->body), 76, "\r\n");
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
