<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelCorporateAccount;
use App\Modules\TravelAgency\Domain\Models\TravelQuote;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\CorporateBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-803 (#6094) — Comptes corporate & devis.
 *
 * Écritures réservées `travel.manage` ; la création de la réservation
 * groupée passe par `POST /travel/bookings` avec `corporate_account_id`
 * (routée par le contrôleur réservations vers `CorporateBookingService`).
 */
class TravelCorporateController extends Controller
{
    // ── Comptes ─────────────────────────────────────────────────────────────

    public function indexAccounts(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $accounts = TravelCorporateAccount::query()
            ->where('company_id', $actor->company_id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $accounts]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'credit_limit_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $account = TravelCorporateAccount::query()->create([
            'company_id' => $actor->company_id,
            'name' => $data['name'],
            'contact_email' => $data['contact_email'] ?? null,
            'credit_limit_minor' => (int) $data['credit_limit_minor'],
            'currency' => $data['currency'] ?? $actor->company?->currency ?? 'XAF',
            'is_active' => true,
        ]);

        return response()->json(['data' => $account])->setStatusCode(201);
    }

    public function updateAccount(Request $request, TravelCorporateAccount $account): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($account->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $account->update($request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'credit_limit_minor' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $account->refresh()]);
    }

    // ── Devis ───────────────────────────────────────────────────────────────

    public function indexQuotes(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $quotes = TravelQuote::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $quotes]);
    }

    public function storeQuote(Request $request, CorporateBookingService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'corporate_account_id' => ['required', 'integer', 'exists:travel_corporate_accounts,id'],
            'trip_id' => ['required', 'integer', 'exists:travel_trips,id'],
            'class_id' => ['required', 'integer', 'exists:travel_classes,id'],
            'passengers_count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var TravelCorporateAccount $account */
        $account = TravelCorporateAccount::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['corporate_account_id']);

        /** @var TravelTrip $trip */
        $trip = TravelTrip::query()
            ->where('company_id', $actor->company_id)
            ->findOrFail($data['trip_id']);

        $quote = $service->createQuote(
            $account,
            $trip,
            (int) $data['class_id'],
            (int) $data['passengers_count'],
            $actor,
        );

        return response()->json(['data' => $quote])->setStatusCode(201);
    }

    public function acceptQuote(Request $request, TravelQuote $quote, CorporateBookingService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quote->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        return response()->json(['data' => $service->acceptQuote($quote, $actor)]);
    }

    public function cancelQuote(Request $request, TravelQuote $quote, CorporateBookingService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quote->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        return response()->json(['data' => $service->cancelQuote($quote)]);
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
    }
}
