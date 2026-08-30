<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\EduManager\Domain\Models\EduGuardian;
use App\Modules\EduManager\Infrastructure\Services\EduGuardianPortalService;
use App\Modules\EduManager\Interfaces\Api\V1\Requests\CreateEduGuardianPortalLinkRequest;
use App\Modules\EduManager\Interfaces\Api\V1\Traits\ChecksEduSolution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portail guardian — EDU-013 (issue #5829).
 *
 * - `createLink` : la direction génère un lien d'accès expirable
 *   (1..30 jours) pour un responsable légal (audit + événement outbox) ;
 * - `summary` : endpoint PUBLIC — le `portal_token` (64 caractères) EST la
 *   credential (pattern AccountingDocumentShare #5428) : ni auth Sanctum ni
 *   TenantMiddleware, résolution O(1), expiration/révocation vérifiées, et
 *   chaque consultation journalisée (edu_portal_access_logs). Le résumé ne
 *   renvoie QUE les enfants autorisés de CE guardian (jamais d'énumération).
 */
class EduGuardianPortalController extends Controller
{
    use ChecksEduSolution;

    public function __construct(private readonly EduGuardianPortalService $portal)
    {
    }

    public function createLink(CreateEduGuardianPortalLinkRequest $request, EduGuardian $guardian): JsonResponse
    {
        $this->assertSolutionActive();

        /** @var Employee $actor */
        $actor = $request->user();
        $this->assertSameTenant($guardian, $actor->company_id);
        $this->authorize('createPortalLink', $guardian);

        $days = (int) ($request->validated()['expires_in_days'] ?? 7);
        $link = $this->portal->createLink($actor, $guardian, $days);

        $url = url('/api/v1/edu-manager/portal/'.$link->portal_token);

        return response()->json([
            'data' => [
                'portal_link_id' => (int) $link->getAttribute('id'),
                'url' => $url,
                'expires_at' => $link->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Endpoint public (token = credential) — résumé du portail guardian.
     */
    public function summary(Request $request, string $token): JsonResponse
    {
        $link = $this->portal->resolveToken($token);

        if ($link === null) {
            abort(404, 'EDU_PORTAL_LINK_NOT_FOUND');
        }

        $this->portal->logAccess($link);

        return response()->json(['data' => $this->portal->summary($link)])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }
}
