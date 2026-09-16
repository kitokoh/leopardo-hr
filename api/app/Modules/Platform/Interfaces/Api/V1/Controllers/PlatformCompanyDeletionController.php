<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Platform\Infrastructure\Services\TenantDeletionInventory;
use App\Modules\Platform\Infrastructure\Services\TenantDeletionService;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * #7475 — Suppression sûre d'un tenant (console plateforme).
 *
 * Trois points d'entrée, dans l'ordre du parcours en deux temps :
 *
 *   GET    /platform/companies/{company}/deletion-inventory
 *          → ce que la suppression détruirait (inventaire chiffré).
 *   GET    /platform/companies/{company}/deletion-audits
 *          → le journal des opérations passées (critère 4 : « consultable »).
 *   DELETE /platform/companies/{company}
 *          → la purge elle-même, après confirmation par ressaisie du nom.
 *
 * Le service porte toute la logique ; ce contrôleur ne fait que valider la
 * demande et la traduire en réponse HTTP.
 */
class PlatformCompanyDeletionController extends Controller
{
    public function __construct(
        private readonly TenantDeletionService $deletionService,
        private readonly TenantDeletionInventory $inventory,
    ) {}

    public function inventory(string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse([
            'data' => $this->inventory->for($company),
        ]);
    }

    public function history(string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse([
            'data' => $this->deletionService->history($company->id),
        ]);
    }

    public function destroy(Request $request, string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        $validated = $request->validate([
            'confirm_name' => ['required', 'string', 'max:200'],
            'mode' => ['nullable', Rule::in([
                TenantDeletionService::MODE_PURGE,
                TenantDeletionService::MODE_ANONYMIZE,
            ])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Critère d'acceptation 2 : la ressaisie du nom exact de la société est
        // la confirmation forte — un simple « êtes-vous sûr ? » ne protège pas
        // d'une suppression de paie.
        if (trim((string) $validated['confirm_name']) !== $company->name) {
            throw ValidationException::withMessages([
                'confirm_name' => (string) __('errors.TENANT_DELETION_CONFIRMATION_MISMATCH'),
            ]);
        }

        $inventory = $this->inventory->for($company);

        $mode = $validated['mode'] ?? null;

        if (! is_string($mode) || $mode === '') {
            // Sans donnée de paie, la purge est le mode évident ; avec de la
            // paie, l'opérateur doit trancher (critère 5).
            $mode = $inventory['has_payroll_data']
                ? ''
                : TenantDeletionService::MODE_PURGE;
        }

        $actor = $request->user();

        if (! $actor instanceof SuperAdmin) {
            abort(403);
        }

        $result = $this->deletionService->destroy(
            $company,
            $mode,
            $actor,
            $this->requestId($request),
            // #7475 (reliquat) — la justification est validée depuis le début
            // mais n'était transmise à personne : elle est désormais persistée
            // sur la piste d'audit (colonne `reason`).
            isset($validated['reason']) && is_string($validated['reason']) ? $validated['reason'] : null,
        );

        return new JsonResponse(['data' => $result], 200);
    }

    /**
     * Corrélation de l'opération (même convention que #5439 sur l'audit).
     */
    private function requestId(Request $request): ?string
    {
        $header = $request->header('X-Request-Id');

        if (is_string($header) && $header !== '') {
            return Str::limit($header, 80, '');
        }

        return null;
    }
}
