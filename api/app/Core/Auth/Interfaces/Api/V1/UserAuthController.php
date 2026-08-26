<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1;

use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserAuthController extends Controller
{
    /** Valid personal status values (#5540). */
    private const VALID_STATUSES = [
        'student',
        'employee',
        'entrepreneur',
        'seeking_employment',
    ];

    public function __construct(
        private readonly UserAuthService $userAuthService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $result = $this->userAuthService->register(
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
            email: $validated['email'],
            password: $validated['password'],
            phone: $validated['phone'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->userAuthService->login(
            email: $validated['email'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ]);
    }

    public function googleSignIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->userAuthService->googleSignIn(
            idToken: $validated['id_token'],
            deviceName: $validated['device_name'] ?? null,
        );

        return new JsonResponse([
            'data' => $this->formatUser($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'is_new' => $result['is_new'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('user_api');

        return new JsonResponse([
            'data' => $this->formatUser($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'preferred_language' => ['sometimes', 'string', 'size:2'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');
        $user->update($validated);

        /** @var User $fresh */
        $fresh = $user->fresh();

        return new JsonResponse([
            'data' => $this->formatUser($fresh),
        ]);
    }

    /**
     * #5540 — Met à jour les statuts personnels cumulables de l'utilisateur.
     *
     * Valeurs acceptées : student, employee, entrepreneur, seeking_employment.
     * Le tableau peut être vide (reset des statuts).
     */
    public function updatePersonalStatuses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'statuses' => ['present', 'array', 'max:4'], // present : autorise le reset à vide (#5540)
            'statuses.*' => ['string', 'in:'.implode(',', self::VALID_STATUSES)],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');

        // Déduplique et réindexe
        $statuses = array_values(array_unique($validated['statuses']));

        $user->update(['personal_statuses' => $statuses]);

        /** @var User $fresh */
        $fresh = $user->fresh();

        return new JsonResponse([
            'data' => $this->formatUser($fresh),
        ]);
    }

    /**
     * #5540 — Recherche publique de tenants (entreprises) par nom.
     *
     * Permet à un utilisateur de trouver une entreprise existante pour
     * envoyer une demande d'intégration (devenir employé).
     * Ne retourne que les noms et UUID — aucune donnée sensible.
     */
    public function searchCompanies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $query = trim($validated['q']);

        // Requête sur la table public.companies (multi-tenant global)
        $companies = Company::query()
            ->select(['id', 'name', 'country', 'city'])
            ->where('name', 'ilike', "%{$query}%")
            ->whereNotNull('name')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return new JsonResponse([
            'data' => $companies->map(fn (Company $c) => [
                'id' => (string) $c->id,
                'name' => $c->name,
                'country' => $c->country,
                'city' => $c->city,
            ])->values(),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user('user_api');

        if ($user->password_hash && ! Hash::check($validated['current_password'], $user->password_hash)) {
            return new JsonResponse([
                'error' => 'INVALID_CURRENT_PASSWORD',
                'message' => __('errors.INVALID_CURRENT_PASSWORD'),
            ], 422);
        }

        // #4695 : password_hash hors $fillable — assignation explicite.
        $user->forceFill(['password_hash' => Hash::make($validated['new_password'])])->save();

        return new JsonResponse(['status' => 'ok']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user('user_api')?->currentAccessToken()?->delete();

        return new JsonResponse(['message' => 'LOGGED_OUT']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_url,
            'provider' => $user->provider,
            'preferred_language' => $user->preferred_language,
            'status' => $user->status,
            'account_type' => 'user',
            // #5540 — statuts cumulables (student / employee / entrepreneur / seeking_employment)
            'personal_statuses' => $user->personal_statuses ?? [],
            'has_company' => $user->employeeLinks()->where('status', 'active')->exists(),
            'company_requests' => $user->companyRequests()
                ->select(['id', 'company_name', 'status', 'type', 'created_at'])
                ->latest()
                ->take(5)
                ->get(),
            'employee_links' => $user->employeeLinks()
                ->with('company:id,name')
                ->where('status', 'active')
                ->get()
                ->map(fn ($link) => [
                    'company_id' => $link->company_id,
                    'company_name' => $link->company?->name,
                    'employee_id' => $link->employee_id,
                ]),
        ];
    }
}
