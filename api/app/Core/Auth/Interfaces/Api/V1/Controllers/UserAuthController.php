<?php

declare(strict_types=1);

namespace App\Core\Auth\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Core\Auth\Infrastructure\Services\UserAuthService;
use App\Core\Auth\Interfaces\Api\V1\Requests\ChangeUserPasswordRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\GoogleSignInRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\LoginUserRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\RegisterUserRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\SearchCompaniesRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\UpdatePersonalStatusesRequest;
use App\Core\Auth\Interfaces\Api\V1\Requests\UpdateUserProfileRequest;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserAuthController extends Controller
{
    public function __construct(
        private readonly UserAuthService $userAuthService,
    ) {}

    public function register(RegisterUserRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{first_name: string, last_name: string, email: string, password: string, phone?: string|null} $validated */
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

    public function login(LoginUserRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{email: string, password: string, device_name?: string|null} $validated */
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

    public function googleSignIn(GoogleSignInRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{id_token: string, device_name?: string|null} $validated */
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

    public function updateProfile(UpdateUserProfileRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

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
    public function updatePersonalStatuses(UpdatePersonalStatusesRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user('user_api');

        /** @var array{statuses: array<int, string>} $validated */
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
    public function searchCompanies(SearchCompaniesRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var array{q: string} $validated */
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

    public function changePassword(ChangeUserPasswordRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user('user_api');

        /** @var array{current_password: string, new_password: string} $validated */
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
