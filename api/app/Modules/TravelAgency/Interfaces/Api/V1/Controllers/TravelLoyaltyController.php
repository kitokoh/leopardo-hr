<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Infrastructure\Services\LoyaltyPointsService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RedeemTravelLoyaltyRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelLoyaltyOptInRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-811 (#6101) — Fidélité voyageur.
 *
 * Opt-in RGPD explicite ; points crédités une seule fois par billet ;
 * solde consultable ; récompenses (conversion points → avoir).
 */
class TravelLoyaltyController extends Controller
{
    public function balance(Request $request, LoyaltyPointsService $service, int $contact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        return response()->json(['data' => $service->balance($contact)]);
    }

    public function optIn(StoreTravelLoyaltyOptInRequest $request, LoyaltyPointsService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $account = $service->optIn((int) $request->validated('contact_id'));

        return response()->json([
            'data' => [
                'contact_id' => $account->contact_id,
                'opted_in' => $account->isOptedIn(),
                'points_balance' => $account->points_balance,
            ],
        ], 201);
    }

    public function optOut(StoreTravelLoyaltyOptInRequest $request, LoyaltyPointsService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $account = $service->optOut((int) $request->validated('contact_id'));

        return response()->json([
            'data' => [
                'contact_id' => $account->contact_id,
                'opted_in' => $account->isOptedIn(),
                'points_balance' => $account->points_balance,
            ],
        ]);
    }

    public function redeem(RedeemTravelLoyaltyRequest $request, LoyaltyPointsService $service, int $contact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $result = $service->redeem(
            contactId: $contact,
            points: (int) $request->validated('points'),
            bookingId: $request->validated('booking_id') !== null ? (int) $request->validated('booking_id') : null,
            reason: $request->validated('reason') !== null ? (string) $request->validated('reason') : 'Récompense fidélité',
        );

        return response()->json(['data' => $result]);
    }
}
