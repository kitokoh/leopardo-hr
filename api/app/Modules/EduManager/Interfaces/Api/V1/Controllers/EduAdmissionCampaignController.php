<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduAdmission;
use App\Modules\EduManager\Infrastructure\Services\EduAdmissionFollowupService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduAdmissionFollowupRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API marketing admissions — EDU-015 (issue #5831).
 *
 * Relances consenties via le CRM/Marketing client (journal idempotent +
 * événements versionnés outbox) et révocation du consentement (opt-out RGPD).
 * Direction uniquement — dossiers contenant des PII d'enfants.
 */
class EduAdmissionCampaignController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduAdmissionFollowupService $followups)
    {
    }

    public function followUp(StoreEduAdmissionFollowupRequest $request, EduAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('followUp', $admission);

        $followup = $this->followups->recordFollowup($actor, $admission, $request->validated());

        return response()->json([
            'data' => [
                'id' => (int) $followup->getAttribute('id'),
                'admission_id' => (int) $followup->getAttribute('admission_id'),
                'campaign_code' => $followup->campaign_code,
                'channel' => $followup->channel,
                'status' => $followup->status,
                'sent_at' => $followup->sent_at->toIso8601String(),
            ],
        ], 201);
    }

    public function optOut(Request $request, EduAdmission $admission): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($admission, $actor->company_id);
        $this->authorize('optOut', $admission);

        $admission = $this->followups->optOut($actor, $admission);

        return response()->json([
            'data' => [
                'id' => (int) $admission->getAttribute('id'),
                'admission_number' => $admission->admission_number,
                'consent_contact' => $admission->consent_contact,
                'consent_revoked_at' => $admission->consent_revoked_at?->toIso8601String(),
            ],
        ]);
    }
}
