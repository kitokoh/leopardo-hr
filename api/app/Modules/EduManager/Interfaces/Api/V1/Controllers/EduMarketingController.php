<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionFollowupService;
use App\Modules\EduManager\Infrastructure\Services\EduMarketingService;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrat marketing des admissions — EDU-015 (issue #5831).
 *
 * EduManager expose aux campagnes d'admission (CRM/Marketing client) les
 * segments de prospects CONSENTIS uniquement (`consent_contact = true`),
 * sans dupliquer la logique Marketing (BC-12/Marketing). L'opt-out est un
 * droit RGPD : il met à jour `consent_contact` sans créer de lien vers le
 * CRM commercial Leopardo (finalité + minimisation).
 */
class EduMarketingController extends Controller
{
    use ChecksEduSolution;

    public function __construct(
        private readonly EduMarketingService $marketing,
        private readonly EduAdmissionFollowupService $followups,
    ) {}

    public function eligibleProspects(Request $request): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', EduAdmission::class);

        $from = $request->filled('from') ? (string) $request->input('from') : null;
        $to = $request->filled('to') ? (string) $request->input('to') : null;

        return response()->json([
            'data' => $this->marketing->marketingEligible($actor, $from, $to),
        ]);
    }

    public function optOut(Request $request, EduAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('update', $admission);

        // Opt-out COMPLET (#5831) : révoquer le consentement ET basculer les
        // relances en cours/à venir (`opted_out`), avec horodatage
        // `consent_revoked_at` et événement d'outbox. L'ancien corps se
        // contentait de remettre `consent_contact` à false : un opt-out
        // laissait les relances vivantes — et la réponse était inexploitable
        // par la vitrine (pas de `consent_revoked_at`).
        $admission = $this->followups->optOut($actor, $admission);

        return response()->json([
            'data' => [
                'id' => (int) $admission->getAttribute('id'),
                'admission_number' => $admission->admission_number,
                'consent_contact' => (bool) $admission->consent_contact,
                'consent_revoked_at' => $admission->consent_revoked_at?->toIso8601String(),
            ],
        ]);
    }
}
