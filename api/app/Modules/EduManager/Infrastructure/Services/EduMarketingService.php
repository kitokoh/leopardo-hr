<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use Illuminate\Support\Carbon;

/**
 * Contrat marketing des admissions — EDU-015 (issue #5831).
 *
 * EduManager expose aux campagnes d'admission (CRM/Marketing client) les
 * segments de prospects CONSENTIS uniquement (`consent_contact = true`),
 * sans dupliquer la logique Marketing (segments, campagnes, relances =
 * BC-12/Marketing). `crm_contact_id` (référence de contrat, EDU-004) permet
 * à Marketing de rattacher la relance au contact CRM sans coupler les tables.
 *
 * RGPD : aucun prospect sans consentement n'apparaît jamais dans un segment ;
 * les relances passent par les canaux autorisés du CRM client (V1).
 */
final class EduMarketingService
{
    /**
     * Prospects éligibles aux campagnes d'admission (consentement explicite).
     *
     * @return array<int, array<string, mixed>>
     */
    public function marketingEligible(Employee $actor, ?string $from, ?string $to): array
    {
        $query = EduAdmission::query()
            ->where('company_id', $actor->company_id)
            ->where('consent_contact', true)
            ->whereIn('status', [
                EduAdmission::STATUS_NEW,
                EduAdmission::STATUS_DOCUMENT_PENDING,
                EduAdmission::STATUS_REVIEW,
                EduAdmission::STATUS_WAITLISTED,
            ]);

        if ($from !== null) {
            $query->where('applied_at', '>=', Carbon::parse($from)->toDateString());
        }
        if ($to !== null) {
            $query->where('applied_at', '<=', Carbon::parse($to)->toDateString());
        }

        return $query
            ->orderByDesc('applied_at')
            ->get(['id', 'admission_number', 'crm_contact_id', 'applicant_first_name', 'applicant_last_name', 'applied_at', 'status'])
            ->map(fn (EduAdmission $admission): array => [
                'admission_id' => (int) $admission->getAttribute('id'),
                'admission_number' => $admission->admission_number,
                'crm_contact_id' => $admission->crm_contact_id,
                'applicant_name' => trim($admission->applicant_first_name.' '.$admission->applicant_last_name),
                'applied_at' => $admission->applied_at?->toDateString(),
                'status' => $admission->status,
            ])
            ->all();
    }
}
