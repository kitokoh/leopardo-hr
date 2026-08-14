<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Support\CountryDefaults;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MULTI-PAYS (#1867) — garde « pays du tenant obligatoire et valide ».
 *
 * Refuse (422) toute opération métier sensible (paie, structures salariales,
 * employés) lorsque le tenant n'a pas de pays légal valide/supporté. Aucun
 * fallback silencieux : un pays absent, inconnu ou non supporté bloque
 * l'écriture plutôt que de calculer avec le mauvais référentiel.
 */
final class RequireTenantCountry
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Employee|null $user */
        $user = $request->user();

        if ($user instanceof Employee) {
            $company = $user->company;

            if ($company instanceof Company && ! CountryDefaults::isSupported($company->country)) {
                return response()->json([
                    'message' => 'Le pays légal du tenant est obligatoire et doit être supporté avant cette opération.',
                    'errors' => ['country' => ['Pays du tenant absent ou non supporté ('.($company->country ?: 'vide').').']],
                ], 422);
            }
        }

        return $next($request);
    }
}
