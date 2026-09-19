<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelCustomerAccount;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Infrastructure\Services\TravelCustomerAccountService;
use App\Shared\Rules\NotCommonPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issue #7739 — Comptes clients GRAND PUBLIC de la marketplace (épic #7736).
 *
 * Auth par tokens Sanctum sur le guard DÉDIÉ `travel_customer` (provider
 * plateforme `travel_customer_accounts`, schéma public) : jamais le guard
 * employés ni super-admin. Login/register sous `throttle:auth-sensitive`
 * (e-mail + IP) + verrouillage 15 min après 5 échecs (pattern #6563).
 *
 * Isolation : « mes réservations » est borné par `customer_account_id` —
 * un client ne voit que SES réservations, et seule la surface publique
 * marketplace est exposée (nom public d'agence, jamais d'identifiant tenant
 * ni de donnée interne d'agence).
 */
class TravelCustomerAccountController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(
        private readonly TravelCustomerAccountService $accounts,
    ) {}

    /**
     * Inscription : hash du mot de passe (jamais en clair) + rattachement
     * des réservations marketplace existantes portant le même e-mail de
     * contact (« à la création » du compte, critère #7739).
     */
    public function register(Request $request): JsonResponse
    {
        // Normalisation avant validation : l'unicité e-mail est insensible à
        // la casse (le compte est toujours stocké en minuscules).
        $rawEmail = $request->input('email');
        if (is_string($rawEmail)) {
            $request->merge(['email' => mb_strtolower($rawEmail)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:travel_customer_accounts,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:255', Password::min(12)->numbers(), new NotCommonPassword],
        ]);

        $account = TravelCustomerAccount::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower((string) $data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make((string) $data['password']),
            'last_login_at' => now(),
        ]);

        $claimed = $this->accounts->claimBookingsByEmail($account);

        return response()->json([
            'data' => [
                'account' => $this->accountPayload($account),
                'token' => $account->createToken('travel-web')->plainTextToken,
                'claimed_bookings' => $claimed,
            ],
        ], 201);
    }

    /**
     * Connexion : throttle route `auth-sensitive` + verrouillage applicatif
     * (5 échecs / 15 min par e-mail + IP, pattern PlatformAuthController
     * #6563) — jamais de distinction « compte inconnu / mot de passe faux ».
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $accountKey = 'travel_customer_login_'.mb_strtolower((string) $data['email']);
        $attemptKey = $accountKey.':'.$request->ip();
        $lockKey = $accountKey.':lock';

        if (Cache::get($lockKey)) {
            return response()->json([
                'message' => __('auth.account_locked'),
            ], 423);
        }

        /** @var TravelCustomerAccount|null $account */
        $account = TravelCustomerAccount::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $data['email'])])
            ->first();

        if (! $account instanceof TravelCustomerAccount || ! Hash::check((string) $data['password'], $account->password)) {
            $attempts = (int) Cache::get($attemptKey, 0) + 1;
            Cache::put($attemptKey, $attempts, now()->addMinutes(15));

            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                Cache::put($lockKey, true, now()->addMinutes(15));
            }

            return response()->json(['message' => __('auth.failed')], 401);
        }

        Cache::forget($attemptKey);
        Cache::forget($lockKey);

        $account->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => [
                'account' => $this->accountPayload($account),
                'token' => $account->createToken('travel-web')->plainTextToken,
            ],
        ]);
    }

    /**
     * Déconnexion : révocation du SEUL token courant (les autres appareils
     * restent connectés).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->authenticated($request)->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['data' => ['logged_out' => true]]);
    }

    /**
     * Profil du client connecté.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['account' => $this->accountPayload($this->authenticated($request))]]);
    }

    /**
     * « Mes réservations » cross-agences, strictement bornées au compte.
     */
    public function bookings(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $bookings = $this->accounts->bookingsFor(
            $this->authenticated($request),
            isset($filters['per_page']) ? (int) $filters['per_page'] : 20,
        );

        /** @var list<TravelBooking> $items */
        $items = $bookings->items();

        /** @var array<string, Company> $agencies */
        $agencies = Company::query()
            ->whereIn('id', collect($items)->map(fn (TravelBooking $booking): string => (string) $booking->company_id)->unique()->values())
            ->get()
            ->keyBy(fn (Company $company): string => (string) $company->id)
            ->all();

        return response()->json([
            'data' => collect($items)
                ->map(fn (TravelBooking $booking): array => $this->bookingPayload($booking, $agencies[(string) $booking->company_id] ?? null))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'last_page' => $bookings->lastPage(),
            ],
        ]);
    }

    /**
     * Client authentifié via le guard dédié — jamais un employé ni un
     * super-admin (les tokens des autres guards ne résolvent pas ce modèle).
     */
    private function authenticated(Request $request): TravelCustomerAccount
    {
        $account = $request->user('travel_customer');

        abort_unless($account instanceof TravelCustomerAccount, 401);

        return $account;
    }

    /**
     * @return array{id: int, name: string, email: string, phone: string|null, created_at: string|null}
     */
    private function accountPayload(TravelCustomerAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'phone' => $account->phone,
            'created_at' => $account->created_at?->toIso8601String(),
        ];
    }

    /**
     * Charge utile publique d'une réservation : référence, statut, trajet,
     * billets (numéros seulement — jamais le code de validation, qui reste
     * le secret de possession de la surface e-billet #7395) et NOM PUBLIC
     * de l'agence. Aucun identifiant tenant.
     *
     * @return array<string, mixed>
     */
    private function bookingPayload(TravelBooking $booking, ?Company $agency): array
    {
        $trip = $booking->trip;
        $route = $trip?->route;

        return [
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
            'passenger_count' => $booking->passenger_count,
            'total_amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'created_at' => $booking->created_at?->toIso8601String(),
            'expires_at' => $booking->expires_at?->toIso8601String(),
            'trip' => $trip === null ? null : [
                'code' => $trip->code,
                'departure_date' => $trip->departure_date->toDateString(),
                'departure_time' => $trip->departure_time,
                'origin_city' => $route?->originCity?->name,
                'destination_city' => $route?->destinationCity?->name,
            ],
            'tickets' => $booking->tickets
                ->map(fn (TravelTicket $ticket): array => [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'status' => $ticket->status->value,
                ])
                ->values()
                ->all(),
            'agency' => ['name' => $agency?->name],
        ];
    }
}
