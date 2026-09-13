<?php

declare(strict_types=1);

namespace App\Modules\HR\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #7322 — auto-activation d'un module horizontal par le client (tenant).
 *
 * Demande produit : « le client doit pouvoir s'auto-activer les modules ».
 * Jusqu'ici, seul l'admin plateforme (`PATCH /platform/companies/{id}/features`)
 * ou le provisioning écrivaient ces deux sources de vérité :
 *   - `metadata.modules` (sélection client, lue par `client-features.ts`) ;
 *   - `companies.features` (flags plateforme : accounting, crm,
 *     company_showcase — cf. `Company::HORIZONTAL_TOOL_FEATURES`).
 *
 * RBAC : responsable du tenant (`principal`/`rh`), miroir de
 * `CompanyBrandingController::update()`. Allowlist FAIL-CLOSED : seule une clé
 * de `Company::HORIZONTAL_TOOLS` est activable (422 sinon, aucune écriture) —
 * les VERTICALES (restaurant, travel, fuel, éducation) restent hors périmètre :
 * elles requièrent des seeders/dépendances de pack (BC-25) et passent par
 * l'admin plateforme.
 *
 * Isolation tenant : l'écriture cible `public.companies` par requête QUALIFIÉE
 * (piège search_path documenté — cf. `CompanyBrandingController::persistMetadata`),
 * et n'agit que sur la société courante (`currentCompany()`).
 */
class CompanyModuleController extends Controller
{
    /**
     * POST /api/v1/company/modules/{module}/activate
     */
    public function activate(Request $request, string $module): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        abort_unless($actor->hasManagerRole('principal', 'rh'), 403);

        $key = strtolower(trim($module));

        if (! in_array($key, Company::HORIZONTAL_TOOLS, true)) {
            // Fail-closed : une clé inconnue (ou une verticale) ne doit jamais
            // produire d'écriture — l'erreur est explicite et localisée.
            throw ValidationException::withMessages([
                'module' => [__('errors.INVALID_HORIZONTAL_TOOL', ['module' => $key])],
            ]);
        }

        $company = $this->freshCompany();

        // Idempotent : `activateHorizontalTool` retourne false si déjà actif.
        $activated = $company->activateHorizontalTool($key);

        $this->persist($company);

        if ($activated) {
            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $actor->id,
                'action' => 'module.activated',
                'auditable_type' => Company::class,
                'auditable_id' => null,
                'old_values' => ['module' => $key, 'active' => false],
                'new_values' => ['module' => $key, 'active' => true],
            ]);
        }

        return new JsonResponse([
            'data' => [
                'module' => $key,
                'activated' => $activated,
                'already_active' => ! $activated,
                'modules' => $this->freshCompany()->metadata['modules'] ?? new \stdClass,
                'features' => $this->freshCompany()->features ?? new \stdClass,
            ],
            'message' => $activated
                ? __('errors.MODULE_ACTIVATED', ['module' => $key])
                : __('errors.MODULE_ALREADY_ACTIVE', ['module' => $key]),
        ]);
    }

    /**
     * Relit la société courante depuis la table qualifiée : le modèle `Company`
     * résolu par `search_path` (contexte tenant) pointerait vers le mauvais
     * schéma (piège documenté #7322 / `CompanyBrandingController`).
     */
    private function freshCompany(): Company
    {
        $company = currentCompany();

        return Company::query()
            ->from($this->companiesTable())
            ->where('id', $company->id)
            ->firstOrFail();
    }

    private function persist(Company $company): void
    {
        DB::table($this->companiesTable())
            ->where('id', $company->id)
            ->update([
                'metadata' => json_encode($company->metadata ?? [], JSON_THROW_ON_ERROR),
                'features' => json_encode($company->features ?? [], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    private function companiesTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
    }
}
