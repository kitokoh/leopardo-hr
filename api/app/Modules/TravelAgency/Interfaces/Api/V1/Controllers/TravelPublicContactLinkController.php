<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * TRAVEL-913 (#6425) — Génération du lien public signé du formulaire de
 * contact voyageurs (visiteur → demande). Direction uniquement (rôle
 * manager). Le lien porte le `company_id` signé et expire (défaut 24 h,
 * borne 1 h – 168 h). Pattern : RestaurantPublicMenuLinkController (RESTO-805).
 */
class TravelPublicContactLinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $expiresInHours = (int) ($request->input('expires_in_hours') ?? 24);
        $expiresInHours = max(1, min(168, $expiresInHours));

        $contactUrl = URL::temporarySignedRoute(
            'travel.public.contact.store',
            now()->addHours($expiresInHours),
            ['company_id' => $actor->company_id],
        );

        return response()->json([
            'data' => [
                'contact_url' => $contactUrl,
                'expires_at' => now()->addHours($expiresInHours)->toIso8601String(),
            ],
        ], 201);
    }
}
