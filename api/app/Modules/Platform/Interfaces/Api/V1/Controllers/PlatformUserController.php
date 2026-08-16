<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * QA wave 2026-08-14 — T004 (#2229).
 *
 * CRUD des utilisateurs plateforme (super_admins) pour l'admin SPA.
 * Auth : guard `super_admin_api` (routes /platform/*). Jamais de suppression
 * physique — `destroy` passe le compte en `deactivated`. Chaque mutation est
 * auditée.
 */
class PlatformUserController extends Controller
{
    private const STATUSES = ['active', 'deactivated', 'suspended'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = SuperAdmin::query();

        if (! empty($validated['search'])) {
            $search = (string) $validated['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $users = $query->orderByDesc('id')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        $items = collect($users->items());
        $companiesByEmail = $this->linkedCompaniesByEmail($items->pluck('email')->all());

        return new JsonResponse([
            'data' => $items->map(fn (SuperAdmin $user): array => $this->serialize($user, $companiesByEmail))->values(),
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
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('super_admins', 'email')],
            'password' => ['required', 'string', 'min:12'],
        ]);

        $user = SuperAdmin::query()->create([
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
        ]);
        // #4695 : password_hash hors $fillable — assignation explicite.
        $user->forceFill(['password_hash' => Hash::make($validated['password'])])->save();
        // Issue #3597 : status non mass-assignable — assignation explicite.
        $user->status = 'active';
        $user->save();

        $this->audit($request, $user, 'platform_user_created');

        return (new JsonResponse(['data' => $this->serialize($user->fresh() ?? $user)]))->setStatusCode(201);
    }

    public function show(Request $request, SuperAdmin $user): JsonResponse
    {
        $companiesByEmail = $this->linkedCompaniesByEmail([$user->email]);

        return new JsonResponse(['data' => $this->serialize($user, $companiesByEmail)]);
    }

    public function update(Request $request, SuperAdmin $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('super_admins', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:12'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        if (isset($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
            unset($validated['password']);
        }
        if (isset($validated['email'])) {
            $validated['email'] = mb_strtolower($validated['email']);
        }

        // Issue #3597 : status non mass-assignable — assignation explicite.
        // Issue #4695 : password_hash non mass-assignable — assignation directe.
        $user->update(Arr::except($validated, ['status', 'password_hash']));
        if (isset($validated['password_hash'])) {
            $user->password_hash = $validated['password_hash'];
        }
        if (array_key_exists('status', $validated)) {
            $user->status = $validated['status'];
        }
        $user->save();

        $this->audit($request, $user, 'platform_user_updated');

        return new JsonResponse(['data' => $this->serialize($user->fresh() ?? $user)]);
    }

    /**
     * Jamais de suppression physique : le compte est désactivé.
     */
    public function destroy(Request $request, SuperAdmin $user): JsonResponse
    {
        if ($user->id === (int) $request->user()?->getAuthIdentifier()) {
            return new JsonResponse(['message' => __('errors.CANNOT_DISABLE_OWN_ACCOUNT')], 422);
        }

        $user->forceFill(['status' => 'deactivated'])->save();
        // Sécurité #2630 : désactiver un compte révoque ses tokens actifs.
        $user->tokens()->delete();

        $this->audit($request, $user, 'platform_user_deactivated');

        return new JsonResponse(null, 204);
    }

    public function activate(Request $request, SuperAdmin $user): JsonResponse
    {
        $user->forceFill(['status' => 'active'])->save();
        $this->audit($request, $user, 'platform_user_activated');

        return new JsonResponse(['data' => $this->serialize($user->fresh() ?? $user)]);
    }

    public function deactivate(Request $request, SuperAdmin $user): JsonResponse
    {
        if ($user->id === (int) $request->user()?->getAuthIdentifier()) {
            return new JsonResponse(['message' => __('errors.CANNOT_DISABLE_OWN_ACCOUNT')], 422);
        }

        $user->forceFill(['status' => 'deactivated'])->save();
        // Sécurité #2630 : désactiver un compte révoque ses tokens actifs.
        $user->tokens()->delete();
        $this->audit($request, $user, 'platform_user_deactivated');

        return new JsonResponse(['data' => $this->serialize($user->fresh() ?? $user)]);
    }

    public function suspend(Request $request, SuperAdmin $user): JsonResponse
    {
        if ($user->id === (int) $request->user()?->getAuthIdentifier()) {
            return new JsonResponse(['message' => __('errors.CANNOT_SUSPEND_OWN_ACCOUNT')], 422);
        }

        $user->forceFill(['status' => 'suspended'])->save();
        // Sécurité #2630 : suspendre un compte révoque ses tokens actifs.
        $user->tokens()->delete();
        $this->audit($request, $user, 'platform_user_suspended');

        return new JsonResponse(['data' => $this->serialize($user->fresh() ?? $user)]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $companiesByEmail
     * @return array<string, mixed>
     */
    private function serialize(SuperAdmin $user, array $companiesByEmail = []): array
    {
        $email = mb_strtolower(trim($user->email));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status ?? 'active',
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'company' => $companiesByEmail[$email] ?? null,
        ];
    }

    /**
     * Resolve the employee link through the canonical public-user email, not
     * through a shared numeric ID between the super_admins and users tables.
     *
     * @param  array<int, mixed>  $emails
     * @return array<string, array<string, mixed>>
     */
    private function linkedCompaniesByEmail(array $emails): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('user_employee_links') || ! Schema::hasTable('companies')) {
            return [];
        }

        $normalizedEmails = collect($emails)
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedEmails === []) {
            return [];
        }

        $links = DB::table('users as u')
            ->join('user_employee_links as l', 'l.user_id', '=', 'u.id')
            ->leftJoin('companies as c', 'c.id', '=', 'l.company_id')
            ->whereIn(DB::raw('LOWER(u.email)'), $normalizedEmails)
            ->orderByDesc('l.id')
            ->get([
                'u.email',
                'l.company_id',
                'l.employee_id',
                'l.status as link_status',
                'c.name as company_name',
            ]);

        $companies = [];
        foreach ($links as $link) {
            $email = mb_strtolower(trim((string) $link->email));
            if (isset($companies[$email])) {
                continue;
            }

            $companies[$email] = [
                'id' => $link->company_id,
                'name' => $link->company_name,
                'employee_id' => $link->employee_id,
                'link_status' => $link->link_status,
            ];
        }

        return $companies;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function audit(Request $request, SuperAdmin $user, string $action, array $changes = []): void
    {
        try {
            AuditLog::query()->create([
                'company_id' => null,
                'user_id' => null,
                'action' => $action,
                'auditable_type' => SuperAdmin::class,
                'auditable_id' => $user->id,
                'old_values' => null,
                'new_values' => ['email' => $user->email, 'status' => $user->status, ...$changes],
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'metadata' => ['actor' => (string) ($request->user()?->getAuthIdentifier() ?? 'system')],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
