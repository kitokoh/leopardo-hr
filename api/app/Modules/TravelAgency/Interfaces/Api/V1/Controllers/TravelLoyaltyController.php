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
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyReward
use App\Modules\TravelAgency\Infrastructure\Services\TravelLoyaltyService
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\LoyaltyAccountRequest
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RedeemLoyaltyRequest
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreLoyaltyRewardRequest;
use App\Modules\TravelAgency\Infrastructure\Services\TravelLoyaltyService
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\LoyaltyAccountRequest
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RedeemLoyaltyRequest

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

    public function account(Request $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $contact = (string) $request->query('contact_identifier', '');

        if ($contact === '') {
            abort(422, 'contact_identifier requis.');
        }

        $account = TravelLoyaltyAccount::query()
            ->where('company_id', $actor->company_id)
            ->where('contact_identifier', $contact)
            ->first();

        return response()->json([
            'data' => [
                'contact_identifier' => $contact,
                'opt_in' => $account->opt_in ?? false,
                'points_balance' => $service->balance((string) $actor->company_id, $contact),
            ],
        ]);
    }


    public function entries(Request $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $contact = (string) $request->query('contact_identifier', '');

        if ($contact === '') {
            abort(422, 'contact_identifier requis.');
        }

        $entries = $service->entries((string) $actor->company_id, $contact);

        return response()->json([
            'data' => array_map(fn ($entry): array => [
                'id' => $entry->id,
                'points' => $entry->points,
                'type' => $entry->type,
                'reason' => $entry->reason,
                'created_at' => $entry->created_at?->toIso8601String(),
            ], $entries),
        ]);
    }


    public function rewards(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $rewards = TravelLoyaltyReward::query()
            ->where('company_id', $actor->company_id)
            ->where('active', true)
            ->orderBy('points_cost')
            ->get();

        return response()->json([
            'data' => $rewards->map(fn (TravelLoyaltyReward $reward): array => [
                'id' => $reward->id,
                'name' => $reward->name,
                'description' => $reward->description,
                'points_cost' => $reward->points_cost,
            ]),
        ]);
    }


    public function storeReward(StoreLoyaltyRewardRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }

        $reward = TravelLoyaltyReward::query()->create(
            array_merge($request->validated(), ['company_id' => $actor->company_id]),
        );

        return response()->json(['data' => [
            'id' => $reward->id,
            'name' => $reward->name,
            'points_cost' => $reward->points_cost,
        ]])->setStatusCode(201);
    }


}