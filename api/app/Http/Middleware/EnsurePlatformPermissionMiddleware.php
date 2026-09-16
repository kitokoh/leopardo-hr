<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #7553 — garde de permission interne à la plateforme.
 *
 * Usage : `->middleware('platform.permission:team.manage')`.
 * Plusieurs permissions (OU logique) : `platform.permission:plans.view,billing.manage`.
 *
 * Sémantique fail-closed : sans compte plateforme identifié → 403 ; compte
 * plateforme dont le rôle ne porte aucune des permissions exigées → 403 avec
 * la liste des permissions requises et le rôle courant (le SPA admin
 * affiche/désactive l'écran concerné sur cette base).
 *
 * La permission `team.manage` n'est portée que par le rôle `super_admin`
 * (@see PlatformRole::permissions()) : les autres rôles ne peuvent ni
 * distribuer ni modifier les rôles de l'équipe plateforme.
 */
final class EnsurePlatformPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user('super_admin_api');

        if (! $user instanceof SuperAdmin) {
            return new JsonResponse([
                'error' => 'PLATFORM_ACCOUNT_REQUIRED',
                'message' => __('errors.PLATFORM_ACCOUNT_REQUIRED'),
            ], 403);
        }

        $required = $this->normalize($permissions);

        if ($required === []) {
            return $next($request);
        }

        $role = $user->platformRole();

        foreach ($required as $permission) {
            if ($role->hasPermission($permission)) {
                return $next($request);
            }
        }

        return new JsonResponse([
            'error' => 'PLATFORM_PERMISSION_REQUIRED',
            'message' => __('errors.PLATFORM_PERMISSION_REQUIRED'),
            'required_permissions' => $required,
            'platform_role' => $role->value,
        ], 403);
    }

    /**
     * Laravel éclate déjà les arguments sur la virgule ; on accepte en plus le
     * séparateur `|` (parité avec `delivery.permission`) et on ignore les
     * entrées vides.
     *
     * @param  array<array-key, string>  $permissions  variadique Laravel
     * @return list<string>
     */
    private function normalize(array $permissions): array
    {
        $normalized = [];

        foreach ($permissions as $group) {
            $parts = preg_split('/[|,]/', $group);

            foreach ($parts === false ? [] : $parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                // Une permission inconnue ne doit jamais élargir l'accès :
                // elle est conservée telle quelle et ne matchera rien.
                $normalized[] = $part;
            }
        }

        /** @var list<string> $unique */
        $unique = array_values(array_unique($normalized));

        return $unique;
    }
}
