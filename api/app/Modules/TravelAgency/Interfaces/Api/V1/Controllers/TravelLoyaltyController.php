<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyEntry;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyReward;
use App\Modules\TravelAgency\Infrastructure\Services\TravelLoyaltyService;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RedeemLoyaltyRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\RedeemTravelLoyaltyRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreLoyaltyRewardRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelLoyaltyOptInRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-811 (#6101) — Fidélité voyageur.
 *
 * Opt-in RGPD explicite ; points crédités une seule fois par billet ; solde
 * consultable ; récompenses (conversion points → avoir).
 *
 * #7445 — **une seule fidélité**. La surface appelait deux implémentations
 * concurrentes : `TravelLoyaltyService` (clé `contact_identifier`, journal
 * `travel_loyalty_entries` — la seule qui existe en base) et
 * `LoyaltyPointsService` (clé `contact_id`, journal `travel_loyalty_transactions`
 * — table qu'aucune migration ne crée). Les points, l'opt-in, l'opt-out, le
 * solde et l'échange passent désormais **tous** par `TravelLoyaltyService`.
 *
 * Conséquence de bord corrigée : `/loyalty/account`, `/loyalty/redeem` et
 * `/loyalty/{contact}` étaient capturés par la route joker `{contact}` (typée
 * `int`) ou appelaient une méthode exigeant un paramètre de route absent →
 * 500. Les segments réservés sont maintenant exclus de la route joker.
 */
class TravelLoyaltyController extends Controller
{
    /** GET /loyalty/{contact} — solde d'un contact. */
    public function balance(Request $request, TravelLoyaltyService $service, string $contact): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $account = $service->findAccount((string) $actor->company_id, $contact);

        // `findAccount()` rend `null` tant que le contact n'a pas consenti :
        // l'absence de compte est un solde à zéro, jamais une erreur.
        return response()->json(['data' => $account instanceof TravelLoyaltyAccount
            ? [
                'contact_identifier' => $account->contact_identifier,
                'points_balance' => $account->points_balance,
                'opted_in' => $account->isOptedIn(),
            ]
            : [
                'contact_identifier' => $contact,
                'points_balance' => 0,
                'opted_in' => false,
            ]]);
    }

    /** POST /loyalty/opt-in — consentement explicite (RGPD). */
    public function optIn(StoreTravelLoyaltyOptInRequest $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $account = $service->optIn(
            (string) $actor->company_id,
            (string) $request->validated('contact_identifier'),
        );

        return response()->json(['data' => $this->accountPayload($account)]);
    }

    /** POST /loyalty/opt-out — retrait du consentement (le solde reste lisible). */
    public function optOut(StoreTravelLoyaltyOptInRequest $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $account = $service->optOut(
            (string) $actor->company_id,
            (string) $request->validated('contact_identifier'),
        );

        return response()->json(['data' => $this->accountPayload($account)]);
    }

    /**
     * POST /loyalty/{contact}/redeem — conversion directe de points en avoir
     * (`points`), sans catalogue de récompenses.
     */
    public function redeemPoints(
        RedeemTravelLoyaltyRequest $request,
        TravelLoyaltyService $service,
        string $contact,
    ): JsonResponse {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $reason = $request->validated('reason');

        $result = $service->redeemPoints(
            (string) $actor->company_id,
            $contact,
            (int) $request->validated('points'),
            $request->validated('booking_id') !== null ? (int) $request->validated('booking_id') : null,
            is_string($reason) && $reason !== '' ? $reason : TravelLoyaltyService::DEFAULT_REDEEM_REASON,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * POST /loyalty/redeem — échange contre une **récompense** du catalogue
     * (débit idempotent par réservation).
     */
    public function redeemReward(RedeemLoyaltyRequest $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelLoyaltyAccount::class)) {
            abort(403);
        }

        $entry = $service->redeem(
            (string) $actor->company_id,
            (string) $request->validated('contact_identifier'),
            (int) $request->validated('reward_id'),
            (int) $request->validated('booking_id'),
        );

        // Solde recalculé côté service (null-safe) : un compte peut ne pas
        // encore exister après l'échange, le débit reste la source de vérité.
        $pointsBalance = $service->balance(
            (string) $actor->company_id,
            (string) $request->validated('contact_identifier'),
        );

        return response()->json(['data' => [
            'id' => $entry->id,
            'type' => $entry->type,
            'points' => $entry->points,
            'booking_id' => $entry->booking_id,
            'points_balance' => $pointsBalance,
        ]]);
    }

    /** GET /loyalty/account?contact_identifier=… — solde du contact connecté au guichet. */
    public function account(Request $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $contact = trim((string) $request->query('contact_identifier', ''));

        if ($contact === '') {
            abort(422, 'contact_identifier requis.');
        }

        $account = $service->findAccount((string) $actor->company_id, $contact);

        // Un contact sans compte n'est pas une erreur : opt-in à faux, solde 0.
        return response()->json(['data' => $account instanceof TravelLoyaltyAccount
            ? [
                'contact_identifier' => $contact,
                'opt_in' => $account->isOptedIn(),
                'points_balance' => $account->points_balance,
            ]
            : [
                'contact_identifier' => $contact,
                'opt_in' => false,
                'points_balance' => 0,
            ]]);
    }

    /** GET /loyalty/entries?contact_identifier=… — journal des points. */
    public function entries(Request $request, TravelLoyaltyService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $contact = trim((string) $request->query('contact_identifier', ''));

        if ($contact === '') {
            abort(422, 'contact_identifier requis.');
        }

        $entries = $service->entries((string) $actor->company_id, $contact);

        return response()->json([
            'data' => array_map(fn (TravelLoyaltyEntry $entry): array => [
                'id' => $entry->id,
                'points' => $entry->points,
                'type' => $entry->type,
                'reason' => $entry->reason,
                'created_at' => $entry->created_at->toIso8601String(),
            ], $entries),
        ]);
    }

    /** GET /loyalty/rewards — catalogue actif du tenant. */
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

    /** POST /loyalty/rewards — catalogue (manager). */
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

    /**
     * @return array{contact_identifier: string, opted_in: bool, points_balance: int}
     */
    private function accountPayload(TravelLoyaltyAccount $account): array
    {
        return [
            'contact_identifier' => (string) $account->contact_identifier,
            'opted_in' => $account->isOptedIn(),
            'points_balance' => $account->points_balance,
        ];
    }
}
