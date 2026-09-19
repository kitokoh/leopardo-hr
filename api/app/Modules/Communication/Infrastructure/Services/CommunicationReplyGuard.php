<?php

declare(strict_types=1);

namespace App\Modules\Communication\Infrastructure\Services;

use App\Modules\Communication\Domain\Models\CommunicationFollowUpOptOut;
use App\Modules\Communication\Domain\Models\CommunicationIntegration;
use App\Modules\Communication\Domain\Models\CommunicationPendingReply;
use App\Shared\Contracts\Crm\EmailFollowUpConsentGate;
use Illuminate\Support\Carbon;

/**
 * Garde-fous d'ENVOI des reponses assistees (BC-29 COMMUNICATION, R5 #7690
 * — spec §3.5 : « auto = envoi direct sous garde-fous R4 »), evalues juste
 * avant chaque envoi (auto ET approbation humaine — l'etat peut changer
 * entre la proposition et la decision).
 *
 * Verdicts TERMINAUX (la proposition est fermee + auditee) :
 * - `integration_inactive` : boite revoquee/en erreur -> `skipped` ;
 * - `missing_send_scope`   : la boite n'a pas accorde `gmail.send` ->
 *                            `skipped` (422 cote approbation humaine) ;
 * - `opted_out`            : destinataire opt-out local (table R4,
 *                            protectrice pour TOUT envoi automatise) ;
 * - `consent_blocked`      : consentement CRM retire / unsubscribe
 *                            (contrat partage `EmailFollowUpConsentGate`).
 *
 * Verdicts TEMPORELS (mode auto UNIQUEMENT : la proposition reste
 * `pending` et bascule dans la file de confirmation humaine — jamais
 * rejouee automatiquement, prudence) :
 * - `quiet_hours`            : fenetre calme (config follow_ups R4) ;
 * - `auto_daily_cap_reached` : plafond journalier d'envois AUTO de la boite.
 *
 * Une APPROBATION HUMAINE explicite ignore les verdicts temporels
 * (l'utilisateur assume l'envoi immediat) mais JAMAIS les terminaux :
 * opt-out et consentement restent inviolables.
 */
class CommunicationReplyGuard
{
    public const VERDICT_SEND = 'send';

    public const VERDICT_SKIP = 'skip';

    public const VERDICT_DEFER = 'defer';

    public function __construct(private readonly EmailFollowUpConsentGate $consent) {}

    /**
     * @param  bool  $manual  true = approbation humaine explicite (les
     *                        verdicts temporels ne s'appliquent pas)
     * @return array{verdict: string, reason: string|null}
     */
    public function evaluate(CommunicationPendingReply $reply, bool $manual = false): array
    {
        /** @var CommunicationIntegration|null $integration */
        $integration = $reply->integration;

        if ($integration === null || ! $integration->isActive()) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'integration_inactive'];
        }

        if (! $integration->hasSendScope()) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'missing_send_scope'];
        }

        if ($this->isOptedOut($reply)) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'opted_out'];
        }

        if (! $this->consent->allowsFollowUp((string) $reply->company_id, $reply->to_email)) {
            return ['verdict' => self::VERDICT_SKIP, 'reason' => 'consent_blocked'];
        }

        if (! $manual && $this->inQuietHours()) {
            return ['verdict' => self::VERDICT_DEFER, 'reason' => 'quiet_hours'];
        }

        if (! $manual && $this->autoDailyCapReached($reply)) {
            return ['verdict' => self::VERDICT_DEFER, 'reason' => 'auto_daily_cap_reached'];
        }

        return ['verdict' => self::VERDICT_SEND, 'reason' => null];
    }

    /**
     * Opt-out local R4 : protecteur pour TOUT envoi automatise du module
     * (relances ET reponses) — un destinataire qui a demande le silence ne
     * recoit pas non plus de reponse generee.
     */
    private function isOptedOut(CommunicationPendingReply $reply): bool
    {
        return CommunicationFollowUpOptOut::query()
            ->withoutGlobalScopes()
            ->where('company_id', $reply->company_id)
            ->where('email', mb_strtolower($reply->to_email))
            ->exists();
    }

    /**
     * Fenetre calme partagee avec les relances R4 (config
     * communication.follow_ups.quiet_hours) : aucun envoi AUTO la nuit.
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
     * Plafond journalier des envois AUTO d'une boite (config
     * communication.replies.auto_daily_cap) — non contournable par les
     * politiques : c'est la borne protectrice du mode opt-in.
     */
    private function autoDailyCapReached(CommunicationPendingReply $reply): bool
    {
        $cap = (int) config('communication.replies.auto_daily_cap', 25);

        if ($cap <= 0) {
            return true;
        }

        $sentToday = CommunicationPendingReply::query()
            ->withoutGlobalScopes()
            ->where('company_id', $reply->company_id)
            ->where('integration_id', $reply->integration_id)
            ->where('mode', CommunicationPendingReply::MODE_AUTO)
            ->where('status', CommunicationPendingReply::STATUS_SENT)
            ->whereDate('sent_at', Carbon::today())
            ->count();

        return $sentToday >= $cap;
    }
}
