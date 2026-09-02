<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Infrastructure\Services\EduGuardianPortalService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\StoreEduGuardianAccessLinkRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portail responsable légal — Issue #5829 (EDU-013).
 *
 * - POST /edu-manager/guardians/{guardian}/access-links : émission d'un lien
 *   expirable à usage unique par la direction (policy EduGuardianPolicy).
 * - POST /edu-manager/guardian-portal/access-links/{token}/consume : route
 *   PUBLIQUE (le token EST le secret) — consommation atomique, replay 410,
 *   consentement RGPD et audit. Aucune énumération d'élèves possible.
 */
class EduGuardianPortalController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduGuardianPortalService $portal)
    {
    }

    public function createAccessLink(StoreEduGuardianAccessLinkRequest $request, EduGuardian $guardian): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('createAccessLink', $guardian);

        $result = $this->portal->createAccessLink($actor, $guardian, $request->validated());

        return response()->json([
            'data' => [
                'id' => (int) $result['link']->getAttribute('id'),
                'guardian_id' => (int) $result['link']->guardian_id,
                'purpose' => $result['link']->purpose,
                'expires_at' => $result['link']->expires_at?->toIso8601String(),
                'expires_in_days' => max(1, (int) $result['link']->expires_at->diffInDays(now())),
                // Token brut : renvoyé UNE seule fois, jamais persisté.
                'token' => $result['token'],
                'portal_url' => url('/guardian-portal?token='.$result['token']),
            ],
        ], 201);
    }

    public function consume(Request $request, string $token): JsonResponse
    {
        $payload = $this->portal->consume($token, $request);

        return response()->json(['data' => $payload]);
    }
}
