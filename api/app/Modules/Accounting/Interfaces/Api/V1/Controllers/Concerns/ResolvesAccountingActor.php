<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1\Controllers\Concerns;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Http\Request;

/**
 * #9763 — Résolution de l'acteur du module Comptabilité, alignée sur
 * `EnsureApiManagerMiddleware` (#7761).
 *
 * Le middleware ouvre un module à un collaborateur **non-manager** dès qu'il
 * porte un grant `employee_module_grants` (`module:accounting`), et le vérifie
 * AVANT l'exigence manager. Quatre contrôleurs refaisaient ensuite
 * `if (! $actor->isManager()) { abort(403); }`, ce qui **annulait** la
 * délégation que le middleware venait d'accorder : un commercial avec le grant
 * `accounting` recevait 403 sur tout le module.
 *
 * Ce trait applique la même règle que le middleware — manager historique
 * (`principal`, `comptable`) **OU** grant explicite du module — en un seul
 * endroit, pour que les quatre contrôleurs ne puissent plus diverger.
 *
 * Le `principal` a implicitement tous les grants (`Employee::hasModuleGrant`).
 */
trait ResolvesAccountingActor
{
    /**
     * Acteur autorisé du tenant courant.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 si ni
     *                                                               manager ni porteur du grant `accounting`.
     */
    protected function accountingActor(Request $request): Employee
    {
        /** @var Employee|null $actor */
        $actor = $request->user();

        if ($actor === null) {
            abort(403);
        }

        if ($actor->isManager() || $actor->hasModuleGrant('accounting')) {
            return $actor;
        }

        abort(403);
    }
}
