<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * RESTO-805 (#6226) — génération du lien public signé (menu + commande en
 * ligne) pour une branche. Direction uniquement (RestaurantBranchPolicy).
 * Le lien porte le `company_id` signé et expire (défaut 24 h).
 */
class RestaurantPublicMenuLinkController extends Controller
{
    public function store(Request $request, RestaurantBranch $branch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($branch->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $branch)) {
            abort(403);
        }

        $expiresInHours = (int) ($request->input('expires_in_hours') ?? 24);
        $expiresInHours = max(1, min(168, $expiresInHours));

        $menuUrl = URL::temporarySignedRoute(
            'restaurant.public.menu',
            now()->addHours($expiresInHours),
            ['company' => $actor->company_id]
        );

        return response()->json([
            'data' => [
                'menu_url' => $menuUrl,
                'expires_at' => now()->addHours($expiresInHours)->toIso8601String(),
            ],
        ], 201);
    }
}
