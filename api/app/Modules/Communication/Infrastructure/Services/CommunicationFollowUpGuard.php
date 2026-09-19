<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Models\CommunicationFollowUp;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpOptOut;
use App\Modules\Communication\Domain\Models\CommunicationFollowUpRule;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationMessage;
use App\Shared\Contracts\Crm\EmailFollowUpConsentGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Garde-fous du moteur de relances (BC-29 COMMUNICATION, R4 #7689 — spec
 * §3.4), evalues DANS LE JOB juste avant l'envoi (jamais uniquement a la
 * planification : l'etat peut changer entre les deux).
 *
 * Verdicts TERMINAUX (l'echeance est fermee + auditee) :
 * - `replied`            : le destinataire a repondu (message ENTRANT non
 *                          auto-soumis posterieur a l'ancre, detecte via la
 *                          sync R2) -> `cancelled` — « jamais si
 *                          l'interlocuteur a repondu » ;
 * - `auto_reply`         : une reponse automatique (RFC 3834) est arrivee —
 *                          sequence gelee par prudence -> `skipped` ;
 * - `mailing_list`       : fil de liste de diffusion (List-Id) -> `skipped` ;
 * - `opted_out`          : destinataire opt-out local -> `skipped` ;
 * - `consent_blocked`    : consentement CRM retire / unsubscribe (contrat
 *                          partage `EmailFollowUpConsentGate`) -> `skipped` ;
 * - `missing_send_scope` : la boite n'a pas accorde `gmail.send` ->
 *                          `skipped` ;
 * - `rule_inactive` / `integration_inactive` : regle desactivee ou boite
 *                          revoquee/en erreur -> `cancelled`.
 *
 * Verdicts TEMPORELS (l'echeance reste `pending`, rejouee plus tard) :
 * - `quiet_hours`        : fenetre calme (config communication.follow_ups) ;
 * - `daily_cap_reached`  : plafond journalier de la boite atteint ;
 * - `contact_cap_reached`: plafond journalier du DESTINATAIRE atteint.
 */
class CommunicationFollowUpGuard
{
    public const VERDICT_SEND = 'send';

    public const VERDICT_SKIP = 'skip';

    public const VERDICT_CANCEL = 'cancel';

    public const VERDICT_DEFER = 'defer';

    public function __construct(private readonly EmailFollowUpConsentGate $consent) {}

    /**
     * @return array{verdict: string, reason: string|null}
     */
    public function evaluate(CommunicationFollowUp $followUp): array
    {
        /** @var CommunicationFollowUpRule|null $rule */
        $rule = $followUp->rule;

        if ($rule === null || ! $rule->active) {
            return ['verdict' => self::VERDICT_CANCEL, 'reason' => 'rule_inactive'];
        }

        /** @var CommunicationIntegration|null $integration */
        $integration = $followUp->integration;

        if ($integration === null || ! $integration->isActive()) {
            return ['verdict' => self::VERDICT_CANCEL, 'reason' => 'integration_inactive'];
        }

        if (! $integration->hasSendScope()) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'missing_send_scope'];
        }

        $reply = $this->replyState($followUp, $integration);

        if ($reply === 'replied') {
            return ['verdict' => self::VERDICT_CANCEL, 'reason' => 'replied'];
        }

        if ($reply === 'auto_reply') {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'auto_reply'];
        }

        if ($reply === 'mailing_list') {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'mailing_list'];
        }

        if ($this->isOptedOut($followUp)) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'opted_out'];
        }

        if (! $this->consent->allowsFollowUp((string) $followUp->company_id, $followUp->contact_email)) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'consent_blocked'];
        }

        if ($this->inQuietHours()) {
            return ['verdict' => self::VERDICT_DEFER, 'reason' => 'quiet_hours'];
        }

        if ($this->dailyCapReached($followUp)) {
            return ['verdict' => self::VERDICT_DEFER, 'reason' => 'daily_cap_reached'];
        }

        if ($this->contactCapReached($followUp)) {
            return ['verdict' => self::VERDICT_DEFER, 'reason' => 'contact_cap_reached'];
        }

        return ['verdict' => self::VERDICT_SEND, 'reason' => null];
    }

    /**
     * Etat du fil depuis le message sortant ancre : `replied` (message
     * entrant humain), `auto_reply` (RFC 3834), `mailing_list` (List-Id)
     * ou `none`.
     */
    private function replyState(CommunicationFollowUp $followUp, CommunicationIntegration $integration): string
    {
        $mailbox = mb_strtolower((string) $integration->email);

        /** @var CommunicationMessage|null $anchor */
        $anchor = $followUp->message_id !== null
            ? CommunicationMessage::query()
                ->withoutGlobalScopes()
                ->where('company_id', $followUp->company_id)
                ->find($followUp->message_id)
            : null;

        $anchorSentAt = $anchor?->sent_at;

        $inbound = CommunicationMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $followUp->company_id)
            ->where('thread_id', $followUp->thread_id)
            ->whereNotNull('sent_at')
            ->when(
                $anchorSentAt !== null,
                fn (Builder $query): Builder => $query->where('sent_at', '>', $anchorSentAt)
            )
            ->orderByDesc('sent_at')
            ->get()
            ->filter(
                fn (CommunicationMessage $message): bool => $message->from_email !== null
                    && mb_strtolower($message->from_email) !== $mailbox
            );

        if ($inbound->contains(fn (CommunicationMessage $message): bool => $message->is_list_message)) {
            return 'mailing_list';
        }

        if ($inbound->contains(fn (CommunicationMessage $message): bool => ! $message->is_auto_reply)) {
            return 'replied';
        }

        if ($inbound->isNotEmpty()) {
            return 'auto_reply';
        }

        return 'none';
    }

    private function isOptedOut(CommunicationFollowUp $followUp): bool
    {
        return CommunicationFollowUpOptOut::query()
            ->withoutGlobalScopes()
            ->where('company_id', $followUp->company_id)
            ->where('email', mb_strtolower($followUp->contact_email))
            ->exists();
    }

    /**
     * Fenetre calme (config communication.follow_ups.quiet_hours) : aucune
     * relance ne part la nuit — `start` > `end` = fenetre nocturne.
     */
    private function inQuietHours(): bool
    {
        $config = (array) config('communication.follow_ups.quiet_hours', []);
        $start = (int) ($config['start'] ?? 20);
        $end = (int) ($config['end'] ?? 8);

        if ($start === $end) {
            return false;
        }

        $timezone = is_string($config['timezone'] ?? null) ? (string) $config['timezone'] : 'UTC';
        $hour = (int) Carbon::now($timezone)->format('G');

        return $start > $end
            ? ($hour >= $start || $hour < $end)   // fenetre nocturne (20h -> 8h)
            : ($hour >= $start && $hour < $end);  // fenetre diurne
    }

    /**
     * Plafond journalier PAR BOITE (toutes regles confondues).
     */
    private function dailyCapReached(CommunicationFollowUp $followUp): bool
    {
        $cap = (int) config('communication.follow_ups.daily_cap_per_user', 25);

        if ($cap <= 0) {
            return false;
        }

        return $this->sentTodayQuery($followUp)
            ->where('integration_id', $followUp->integration_id)
            ->count() >= $cap;
    }

    /**
     * Plafond journalier PAR DESTINATAIRE (tous fils et regles confondus).
     */
    private function contactCapReached(CommunicationFollowUp $followUp): bool
    {
        $cap = (int) config('communication.follow_ups.contact_daily_cap', 1);

        if ($cap <= 0) {
            return false;
        }

        return $this->sentTodayQuery($followUp)
            ->where('contact_email', mb_strtolower($followUp->contact_email))
            ->count() >= $cap;
    }

    /**
     * @return Builder<CommunicationFollowUp>
     */
    private function sentTodayQuery(CommunicationFollowUp $followUp): Builder
    {
        return CommunicationFollowUp::query()
            ->withoutGlobalScopes()
            ->where('company_id', $followUp->company_id)
            ->where('status', CommunicationFollowUp::STATUS_SENT)
            ->where('sent_at', '>=', now()->startOfDay());
    }
}
