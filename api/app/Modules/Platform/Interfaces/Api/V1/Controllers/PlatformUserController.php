<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Gestion des utilisateurs plateforme (comptes `public.users` du portail).
 *
 * Issues #2229 (QA wave 2026-08-14, T004) + #2269 (spec
 * `.specify/features/platform-users-management/`). Le super-admin liste,
 * crée, met à jour et change le statut des comptes — jamais de suppression
 * physique : `destroy()` = désactivation douce (status=disabled), la
 * re-création d'un email désactivé réactive le compte.
 *
 * Toutes les routes sont derrière `auth:super_admin_api` (groupe /platform).
 */
class PlatformUserController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id',
        'first_name',
        'last_name',
        'email',
        'status',
        'created_at',
        'last_login_at',
    ];

    private const STATUSES = ['active', 'disabled', 'suspended'];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $sortBy = $request->input('sort_by', 'id');
        if (! in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            return new JsonResponse([
                'error' => 'INVALID_SORT_FIELD',
                'message' => 'sort_by doit être l\'un des champs allowlistés.',
            ], 422);
        }
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = User::query()
            ->leftJoin('user_employee_links', 'user_employee_links.user_id', '=', 'users.id')
            ->leftJoin('companies', 'companies.id', '=', 'user_employee_links.company_id')
            ->select(
                'users.*',
                'companies.id as linked_company_id',
                'companies.name as linked_company_name',
                'user_employee_links.employee_id as linked_employee_id'
            )
            ->distinct();

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search): void {
                $q->where('users.first_name', 'ilike', $search)
                    ->orWhere('users.last_name', 'ilike', $search)
                    ->orWhere('users.email', 'ilike', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('users.status', $request->input('status'));
        }

        $users = $query->orderBy('users.'.$sortBy, $sortDir)->paginate($perPage);

        return new JsonResponse([
            'data' => $users->getCollection()->map(fn (User $user): array => $this->serialize($user))->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_language' => ['nullable', Rule::in(['fr', 'en', 'ar', 'tr'])],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        // Un compte désactivé peut être réactivé via le même email (jamais de
        // doublon, jamais de suppression physique).
        $existing = User::query()->where('email', $validated['email'])->first();
        if ($existing !== null) {
            return new JsonResponse([
                'error' => 'EMAIL_ALREADY_EXISTS',
                'message' => 'Un compte existe déjà avec cet email.',
            ], 422);
        }

        $user = User::query()->create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password_hash' => isset($validated['password'])
                ? Hash::make($validated['password'])
                : null,
            'phone' => $validated['phone'] ?? null,
            'preferred_language' => $validated['preferred_language'] ?? 'fr',
            'status' => $validated['status'] ?? 'active',
        ]);

        return new JsonResponse(['data' => $this->serialize($user)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->findUserOrFail($id);

        return new JsonResponse(['data' => $this->serialize($user)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->findUserOrFail($id);

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_language' => ['sometimes', Rule::in(['fr', 'en', 'ar', 'tr'])],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        // Garde self-disable (spec #2269, US2) : le super-admin connecté ne
        // peut pas désactiver le compte portail associé à son propre email.
        if (
            isset($validated['status'])
            && in_array($validated['status'], ['disabled', 'suspended'], true)
            && strcasecmp((string) $user->email, (string) $request->user()->email) === 0
        ) {
            return new JsonResponse([
                'error' => 'SELF_DISABLE_FORBIDDEN',
                'message' => 'Un administrateur ne peut pas désactiver son propre compte.',
            ], 422);
        }

        $user->update($validated);

        return new JsonResponse(['data' => $this->serialize($user->fresh())]);
    }

    /**
     * Jamais de suppression physique : désactivation douce (status=disabled).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->findUserOrFail($id);

        if (strcasecmp((string) $user->email, (string) $request->user()->email) === 0) {
            return new JsonResponse([
                'error' => 'SELF_DISABLE_FORBIDDEN',
                'message' => 'Un administrateur ne peut pas supprimer son propre compte.',
            ], 422);
        }

        $user->update(['status' => 'disabled']);

        return new JsonResponse([
            'message' => 'Compte désactivé (aucune suppression physique).',
            'data' => $this->serialize($user->fresh()),
        ]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($id, 'active');
    }

    public function deactivate(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($id, 'disabled');
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        return $this->setStatus($id, 'suspended');
    }

    private function setStatus(int $id, string $status): JsonResponse
    {
        $user = $this->findUserOrFail($id);
        $user->update(['status' => $status]);

        return new JsonResponse(['data' => $this->serialize($user->fresh())]);
    }

    private function findUserOrFail(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'provider' => $user->provider,
            'preferred_language' => $user->preferred_language,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'company' => $user->linked_company_id !== null
                ? [
                    'id' => $user->linked_company_id,
                    'name' => $user->linked_company_name,
                    'employee_id' => $user->linked_employee_id,
                ]
                : null,
        ];
    }
}
