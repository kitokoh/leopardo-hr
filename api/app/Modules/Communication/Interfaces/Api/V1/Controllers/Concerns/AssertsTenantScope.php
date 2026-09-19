<?php

declare(strict_types=1);

namespace App\Modules\Communication\Interfaces\Api\V1\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Garde 404 cross-tenant des bindings implicites (BC-29, R3 #7688).
 *
 * Le binding implicite est resolu AVANT le middleware `tenant`
 * (`SubstituteBindings` global) : le scope `BelongsToCompany` n'est pas
 * encore actif et un modele d'un AUTRE tenant peut etre resolu — la policy
 * repondrait alors 403, revelant l'EXISTENCE de la ressource. Cette garde
 * re-verifie explicitement le tenant et repond 404 (meme pattern que
 * `CrmSegmentController::assertTenantScope` / AccountingContactController).
 */
trait AssertsTenantScope
{
    private function assertTenantScope(Request $request, Model $model): void
    {
        $companyId = $request->user()?->getAttribute('company_id');

        if (is_string($companyId) && (string) $model->getAttribute('company_id') !== $companyId) {
            abort(404);
        }
    }
}
