<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Domain\Models\EduAdmissionFollowup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Issue #5831 (EDU-015) — marketing admissions via le CRM/Marketing client.
 *
 * - Consentement : aucune relance sans `consent_contact` (EDU_CONSENT_REQUIRED)
 *   ; le consentement est figé dans `consent_snapshot` à chaque envoi (RGPD,
 *   finalité et minimisation) ;
 * - Idempotence : (company_id, admission_id, campaign_code, channel) unique —
 *   rejouer une campagne ne duplique jamais une relance ;
 * - Opt-out : révocation du consentement (consent_revoked_at), relances
 *   pending passées à `opted_out`, aucune nouvelle relance possible ensuite ;
 * - Intégration : l'événement versionné `edu.admission.followup.v1` est publié
 *   dans l'outbox EduManager APRÈS le commit — le CRM/Marketing client le
 *   consomme pour l'envoi effectif (adapter de contrat, jamais de connexion
 *   au CRM commercial Leopardo) ; `crm_contact_id` reste une simple référence.
 */
final class EduAdmissionFollowupService
{
    public const EVENT_FOLLOWUP = 'edu.admission.followup.v1';

    public const EVENT_OPTED_OUT = 'edu.admission.opted_out.v1';

    public function __construct(private readonly EduOutboxPublisher $outbox)
    {
    }

    /**
     * Enregistre (idempotemment) une relance consentie sur un dossier.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordFollowup(Employee $actor, EduAdmission $admission, array $data): EduAdmissionFollowup
    {
        if ($admission->company_id !== $actor->company_id) {
            throw new RuntimeException('Admission does not belong to tenant.');
        }

        abort_if(! $admission->consent_contact, 422, 'EDU_CONSENT_REQUIRED');
        abort_if($admission->consent_revoked_at !== null, 422, 'EDU_CONSENT_REVOKED');

        $payload = [
            'company_id' => $actor->company_id,
            'admission_id' => (int) $admission->getAttribute('id'),
            'campaign_code' => $data['campaign_code'],
            'channel' => $data['channel'],
            'status' => EduAdmissionFollowup::STATUS_SENT,
            'consent_snapshot' => [
                'consent_contact' => true,
                'consented_at' => $admission->consented_at?->toIso8601String(),
                'source' => 'edu-admission-'.(int) $admission->getAttribute('id'),
            ],
            'sent_at' => $data['sent_at'] ?? now(),
            'created_by' => $actor->id,
        ];

        try {
            /** @var EduAdmissionFollowup $followup */
            $followup = EduAdmissionFollowup::query()->create($payload);
        } catch (UniqueConstraintViolationException) {
            /** @var EduAdmissionFollowup $followup */
            $followup = EduAdmissionFollowup::query()
                ->where('company_id', $actor->company_id)
                ->where('admission_id', $admission->getAttribute('id'))
                ->where('campaign_code', $data['campaign_code'])
                ->where('channel', $data['channel'])
                ->firstOrFail();

            return $followup;
        }

        $this->outbox->publish($actor->company_id, self::EVENT_FOLLOWUP, [
            'followup_id' => (int) $followup->getAttribute('id'),
            'admission_id' => (int) $admission->getAttribute('id'),
            'campaign_code' => $data['campaign_code'],
            'channel' => $data['channel'],
            'crm_contact_id' => $admission->crm_contact_id,
        ], 'followup-'.(int) $followup->getAttribute('id'));

        return $followup;
    }

    /**
     * Révocation du consentement marketing (opt-out RGPD).
     */
    public function optOut(Employee $actor, EduAdmission $admission): EduAdmission
    {
        if ($admission->company_id !== $actor->company_id) {
            throw new RuntimeException('Admission does not belong to tenant.');
        }

        DB::transaction(function () use ($admission): void {
            if ($admission->consent_contact || $admission->consent_revoked_at === null) {
                $admission->update([
                    'consent_contact' => false,
                    'consent_revoked_at' => now(),
                ]);
            }

            EduAdmissionFollowup::query()
                ->where('company_id', $admission->company_id)
                ->where('admission_id', $admission->getAttribute('id'))
                ->whereIn('status', [EduAdmissionFollowup::STATUS_QUEUED, EduAdmissionFollowup::STATUS_SENT])
                ->update(['status' => EduAdmissionFollowup::STATUS_OPTED_OUT]);
        });

        $this->outbox->publish($actor->company_id, self::EVENT_OPTED_OUT, [
            'admission_id' => (int) $admission->getAttribute('id'),
        ], 'optout-'.(int) $admission->getAttribute('id'));

        return $admission->refresh();
    }
}
