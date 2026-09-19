<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\ClaimPublicCustomerBookingsAction;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPublicCustomer;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Shared\Rules\NotCommonPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * #7739 — Comptes clients GRAND PUBLIC de la marketplace voyage.
 *
 * Inscription / connexion / déconnexion / profil / « mes réservations »
 * cross-agences. Tokens Sanctum DÉDIÉS (guard `travel_customer_api`,
 * provider `travel_customers`) — jamais le guard employés : un token
 * employé ne donne accès à rien ici, et inversement.
 *
 * Sécurité :
 * - mots de passe hashés (bcrypt), politique min 12 + chiffres +
 *   dictionnaire (`NotCommonPassword`), email unique ;
 * - throttling `auth-sensitive` (email+IP) sur register/login + verrouillage
 *   progressif du compte (5 échecs → 15 min), pattern `UserAuthService` ;
 * - « mes réservations » ne rend QUE les réservations du compte
 *   (`public_customer_id`), avec une charge utile minimisée (pattern #7395)
 *   et le seul NOM PUBLIC de l'agence — aucun identifiant tenant.
 *
 * Le checkout INVITÉ reste possible : le rattachement se fait à la création
 * de la réservation (token présent) ou a posteriori par email de contact
 * (`ClaimPublicCustomerBookingsAction`, à l'inscription et à la connexion).
 */
class TravelPublicCustomerAccountController extends Controller
{
    public function register(Request $request, ClaimPublicCustomerBookingsAction $claim): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:travel_public_customers,email'],
            'password' => ['required', 'string', Password::min(12)->numbers(), new NotCommonPassword],
            'phone' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = new TravelPublicCustomer([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => mb_strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
        ]);
        // Pattern #4695 : hash HORS $fillable — assignation explicite (colonne
        // NOT NULL : une seule écriture, jamais de ligne sans hash).
        $customer->forceFill(['password' => Hash::make($validated['password'])]);
        $customer->save();

        // Rattachement « à la création » : les réservations invitées portant
        // cet email de contact sont revendiquées (jamais re-déplacées).
        $claim->execute($customer);

        return new JsonResponse([
            'data' => $this->customerPayload($customer),
            'token' => $this->issueToken($customer, $validated['device_name'] ?? null),
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request, ClaimPublicCustomerBookingsAction $claim): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var TravelPublicCustomer|null $customer */
        $customer = TravelPublicCustomer::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($validated['email'])])
            ->first();

        if (! $customer instanceof TravelPublicCustomer) {
            throw new InvalidCredentialsException;
        }

        if ($customer->locked_until && $customer->locked_until->isFuture()) {
            throw new AccountLockedException($customer->locked_until);
        }

        if ($customer->status !== 'active') {
            // Compte suspendu = aucun token émis (fail-closed, pattern #2618).
            throw new InvalidCredentialsException;
        }

        if (! Hash::check($validated['password'], $customer->password)) {
            $customer->increment('failed_login_attempts');
            if ($customer->failed_login_attempts >= 5) {
                $customer->forceFill(['locked_until' => now()->addMinutes(15)])->save();
            }

            throw new InvalidCredentialsException;
        }

        if ($customer->failed_login_attempts > 0 || $customer->locked_until !== null) {
            $customer->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        }

        // Re-rattachement idempotent : réservations invitées faites APRÈS
        // l'inscription avec le même email de contact.
        $claim->execute($customer);

        return new JsonResponse([
            'data' => $this->customerPayload($customer),
            'token' => $this->issueToken($customer, $validated['device_name'] ?? null),
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $customer->currentAccessToken()->delete();

        return new JsonResponse(['message' => 'Déconnecté.']);
    }

    public function me(Request $request): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->customerPayload($this->currentCustomer($request)),
        ]);
    }

    /**
     * « Mes réservations » CROSS-AGENCES du client connecté.
     *
     * Seules les réservations rattachées au compte (`public_customer_id`)
     * sont rendues — jamais celles d'un autre client ni les réservations
     * invitées non revendiquées. Charge utile minimisée (pattern #7395) +
     * NOM PUBLIC de l'agence uniquement (pattern marketplace #7737).
     */
    public function bookings(Request $request): JsonResponse
    {
        $customer = $this->currentCustomer($request);

        $perPage = max(1, min(50, (int) $request->query('per_page', 20)));

        $bookings = TravelBooking::query()
            ->withoutGlobalScope('company')
            ->where('public_customer_id', $customer->id)
            ->with(['trip.route.originCity', 'trip.route.destinationCity', 'tickets'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        /** @var list<TravelBooking> $items */
        $items = $bookings->items();

        /** @var array<string, Company> $agencies */
        $agencies = Company::query()
            ->whereIn('id', collect($items)->map(fn (TravelBooking $booking): string => (string) $booking->company_id)->unique()->values())
            ->get()
            ->keyBy(fn (Company $company): string => (string) $company->id)
            ->all();

        return new JsonResponse([
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

    private function currentCustomer(Request $request): TravelPublicCustomer
    {
        $customer = $request->user('travel_customer_api');

        abort_unless($customer instanceof TravelPublicCustomer, 401);

        return $customer;
    }

    private function issueToken(TravelPublicCustomer $customer, ?string $deviceName): string
    {
        $customer->forceFill(['last_login_at' => now()])->saveQuietly();

        return $customer->createToken($deviceName ?? 'travel-web')->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerPayload(TravelPublicCustomer $customer): array
    {
        return [
            'id' => $customer->id,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'preferred_language' => $customer->preferred_language,
            'created_at' => $customer->created_at?->toIso8601String(),
        ];
    }

    /**
     * Charge utile MINIMISÉE d'une réservation du client — aucun identifiant
     * tenant, aucune donnée interne d'agence (pattern #7395 / #7737).
     *
     * @return array<string, mixed>
     */
    private function bookingPayload(TravelBooking $booking, ?Company $agency): array
    {
        $route = $booking->trip?->route;

        return [
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
            'booking_source' => $booking->booking_source->value,
            'passenger_count' => $booking->passenger_count,
            'total_amount_minor' => $booking->total_amount_minor,
            'currency' => $booking->currency,
            'created_at' => $booking->created_at?->toIso8601String(),
            'trip' => $booking->trip ? [
                'code' => $booking->trip->code,
                'departure_date' => $booking->trip->departure_date->toDateString(),
                'departure_time' => $booking->trip->departure_time,
                'origin_city' => $route?->originCity?->name,
                'destination_city' => $route?->destinationCity?->name,
            ] : null,
            'tickets' => $booking->tickets->map(fn (TravelTicket $ticket): array => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status->value,
            ])->values()->all(),
            'agency' => ['name' => $agency?->name],
        ];
    }
}
