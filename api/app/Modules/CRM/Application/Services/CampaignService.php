<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\CRM\Domain\Contracts\CampaignConsentCheckerInterface;
use App\Modules\CRM\Domain\Enums\CampaignSendStatus;
use App\Modules\CRM\Domain\Enums\CampaignStatus;
use App\Modules\CRM\Domain\Events\CampaignFinished;
use App\Modules\CRM\Domain\Events\CampaignStarted;
use App\Modules\CRM\Domain\Models\CrmCampaign;
use App\Modules\CRM\Domain\Models\CrmCampaignSend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Campagnes marketing tenant — Issue #5724.
 *
 * Règles :
 *   - cycle de vie strict (draft → scheduled/running → paused/resume →
 *     running → finished | cancelled) — toute transition invalide = 422 ;
 *   - l'audience est résolue au start : segment (#5723) OU snapshot
 *     explicite, PUIS filtrée par consentement (`CampaignConsentChecker`,
 *     fail-closed : jamais d'envoi sans consentement requis) ;
 *   - l'envoi est stoppable (pause/resume/cancel — cancel annule les envois
 *     pending/queued) et observable (`report()` par statut) ;
 *   - découplage CRM ↔ canaux par événements (`CampaignStarted` /
 *     `CampaignFinished`) et par `provider_message_id` — aucun couplage
 *     direct inter-modules ;
 *   - toute mutation sensible est auditée (`audit_logs`, module crm).
 */
final class CampaignService
{
    /** @var list<string> */
    private const CHANNELS = ['email', 'sms', 'whatsapp'];

    public function __construct(private readonly CampaignConsentCheckerInterface $consentChecker) {}

    /**
     * @param  list<int>  $audience
     */
    public function create(
        string $name,
        ?string $description,
        string $channel,
        ?int $segmentId,
        array $audience,
        ?Carbon $scheduledAt,
        ?int $actorId,
    ): CrmCampaign {
        $this->assertChannel($channel);
        $this->assertAudienceSource($segmentId, $audience);

        $status = $scheduledAt !== null
            ? CampaignStatus::Scheduled->value
            : CampaignStatus::Draft->value;

        /** @var CrmCampaign $campaign */
        $campaign = CrmCampaign::query()->create([
            'name' => $name,
            'description' => $description,
            'channel' => $channel,
            'status' => $status,
            'segment_id' => $segmentId,
            'audience_snapshot' => $audience === [] ? null : $audience,
            'scheduled_at' => $scheduledAt,
            'created_by' => $actorId,
        ]);

        $this->audit($campaign, 'campaign.created', [
            'channel' => $channel,
            'status' => $status,
            'segment_id' => $segmentId,
            'audience_explicit' => count($audience),
        ]);

        return $campaign;
    }

    /**
     * @param  list<int>  $audience
     */
    public function update(
        CrmCampaign $campaign,
        string $name,
        ?string $description,
        ?int $segmentId,
        array $audience,
        ?Carbon $scheduledAt,
        ?int $actorId,
    ): CrmCampaign {
        $this->assertEditable($campaign);
        $this->assertChannel($campaign->channel);
        $this->assertAudienceSource($segmentId, $audience);

        $campaign->update([
            'name' => $name,
            'description' => $description,
            'segment_id' => $segmentId,
            'audience_snapshot' => $audience === [] ? null : $audience,
            'scheduled_at' => $scheduledAt,
        ]);

        $this->audit($campaign, 'campaign.updated', ['segment_id' => $segmentId]);

        return $campaign->refresh();
    }

    public function start(CrmCampaign $campaign, ?int $actorId): CrmCampaign
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value, CampaignStatus::Paused->value], true)) {
            throw ValidationException::withMessages(['campaign' => 'Seules les campagnes draft, scheduled ou paused peuvent démarrer.']);
        }

        $audience = $this->resolveAudience($campaign);

        $allowed = [];
        foreach ($audience as $contactId) {
            if ($this->consentChecker->allows($contactId, $campaign->channel)) {
                $allowed[] = $contactId;
            }
        }

        $now = now();

        DB::transaction(function () use ($campaign, $allowed, $now): void {
            CrmCampaignSend::query()->where('campaign_id', $campaign->id)->delete();

            foreach ($allowed as $contactId) {
                CrmCampaignSend::query()->create([
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contactId,
                    'channel' => $campaign->channel,
                    'status' => CampaignSendStatus::Pending->value,
                ]);
            }

            $campaign->update([
                'status' => CampaignStatus::Running->value,
                'audience_snapshot' => $allowed === [] ? null : $allowed,
                'started_at' => $now,
            ]);
        });

        $campaign = $campaign->refresh();

        $this->audit($campaign, 'campaign.started', [
            'audience_requested' => count($audience),
            'audience_allowed' => count($allowed),
            'consent_filtered_out' => count($audience) - count($allowed),
        ]);

        CampaignStarted::dispatch(
            $campaign->company_id,
            $campaign->id,
            $campaign->channel,
            count($allowed),
        );

        return $campaign;
    }

    public function pause(CrmCampaign $campaign, ?int $actorId): CrmCampaign
    {
        if ($campaign->status !== CampaignStatus::Running->value) {
            throw ValidationException::withMessages(['campaign' => 'Seule une campagne running peut être mise en pause.']);
        }

        $campaign->update(['status' => CampaignStatus::Paused->value]);
        $this->audit($campaign, 'campaign.paused', []);

        return $campaign->refresh();
    }

    public function resume(CrmCampaign $campaign, ?int $actorId): CrmCampaign
    {
        if ($campaign->status !== CampaignStatus::Paused->value) {
            throw ValidationException::withMessages(['campaign' => 'Seule une campagne paused peut reprendre.']);
        }

        $campaign->update(['status' => CampaignStatus::Running->value]);
        $this->audit($campaign, 'campaign.resumed', []);

        return $campaign->refresh();
    }

    public function cancel(CrmCampaign $campaign, ?int $actorId): CrmCampaign
    {
        if (in_array($campaign->status, [CampaignStatus::Finished->value, CampaignStatus::Cancelled->value], true)) {
            throw ValidationException::withMessages(['campaign' => 'Campagne déjà terminée.']);
        }

        DB::transaction(function () use ($campaign): void {
            CrmCampaignSend::query()
                ->where('campaign_id', $campaign->id)
                ->whereIn('status', [CampaignSendStatus::Pending->value, CampaignSendStatus::Queued->value])
                ->update(['status' => CampaignSendStatus::Cancelled->value, 'updated_at' => now()]);

            $campaign->update([
                'status' => CampaignStatus::Cancelled->value,
                'finished_at' => now(),
            ]);
        });

        $campaign = $campaign->refresh();

        $this->audit($campaign, 'campaign.cancelled', []);
        CampaignFinished::dispatch($campaign->company_id, $campaign->id, CampaignStatus::Cancelled->value);

        return $campaign;
    }

    public function finish(CrmCampaign $campaign, ?int $actorId): CrmCampaign
    {
        if ($campaign->status !== CampaignStatus::Running->value) {
            throw ValidationException::withMessages(['campaign' => 'Seule une campagne running peut être terminée.']);
        }

        $campaign->update([
            'status' => CampaignStatus::Finished->value,
            'finished_at' => now(),
        ]);

        $campaign = $campaign->refresh();

        $this->audit($campaign, 'campaign.finished', []);
        CampaignFinished::dispatch($campaign->company_id, $campaign->id, CampaignStatus::Finished->value);

        return $campaign;
    }

    /**
     * @return array{total: int, pending: int, sent: int, failed: int, bounced: int, cancelled: int}
     */
    public function report(CrmCampaign $campaign): array
    {
        $counts = CrmCampaignSend::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'total' => (int) array_sum($counts),
            'pending' => (int) ($counts[CampaignSendStatus::Pending->value] ?? 0),
            'sent' => (int) ($counts[CampaignSendStatus::Sent->value] ?? 0),
            'failed' => (int) ($counts[CampaignSendStatus::Failed->value] ?? 0),
            'bounced' => (int) ($counts[CampaignSendStatus::Bounced->value] ?? 0),
            'cancelled' => (int) ($counts[CampaignSendStatus::Cancelled->value] ?? 0),
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveAudience(CrmCampaign $campaign): array
    {
        if ($campaign->segment_id !== null) {
            if (! Schema::hasTable('crm_segment_members')) {
                throw ValidationException::withMessages(['campaign' => 'Segments CRM indisponibles (module segments pas encore migré).']);
            }

            $rows = DB::table('crm_segment_members')
                ->where('segment_id', $campaign->segment_id)
                ->where('company_id', $campaign->company_id)
                ->pluck('contact_id');

            $ids = [];
            foreach ($rows as $id) {
                $ids[] = (int) $id;
            }

            if ($ids === []) {
                throw ValidationException::withMessages(['campaign' => 'Le segment ciblé ne contient aucun membre.']);
            }

            return $ids;
        }

        $audience = $campaign->audience_snapshot ?? [];

        if ($audience === []) {
            throw ValidationException::withMessages(['campaign' => 'Aucune audience : ciblez un segment ou fournissez une liste explicite de contacts.']);
        }

        return $audience;
    }

    private function assertEditable(CrmCampaign $campaign): void
    {
        if (! in_array($campaign->status, [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value, CampaignStatus::Paused->value], true)) {
            throw ValidationException::withMessages(['campaign' => 'Seules les campagnes draft, scheduled ou paused sont modifiables.']);
        }
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw ValidationException::withMessages(['channel' => 'Canal inconnu.']);
        }
    }

    /**
     * @param  list<int>  $audience
     */
    private function assertAudienceSource(?int $segmentId, array $audience): void
    {
        if ($segmentId !== null && $audience !== []) {
            throw ValidationException::withMessages(['campaign' => 'Choisissez un segment OU une audience explicite, pas les deux.']);
        }
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    private function audit(CrmCampaign $campaign, string $action, array $newValues): void
    {
        $userId = Auth::guard('sanctum')->id();

        AuditLog::create([
            'company_id' => $campaign->company_id,
            'user_id' => $userId !== null ? (int) $userId : null,
            'action' => $action,
            'module' => 'crm',
            'auditable_type' => CrmCampaign::class,
            'auditable_id' => $campaign->id,
            'new_values' => $newValues,
            'metadata' => [
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'status' => $campaign->status,
            ],
        ]);
    }
}
