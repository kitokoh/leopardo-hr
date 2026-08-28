<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformCompanyFeatureController extends Controller
{
    public function show(string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company),
                'known_modules' => Company::KNOWN_MODULES,
            ],
        ]);
    }

    public function update(Request $request, string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        $validated = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ]);

        $features = [];
        foreach (Company::KNOWN_MODULES as $module) {
            $features[$module] = $module === 'rh'
                ? true
                : (bool) ($validated['features'][$module] ?? false);
        }

        $previousFeatures = $company->features ?? [];
        $company->features = $features;
        $company->save();

        $this->auditFeatureChange($request, $company, $previousFeatures, $features);

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company->fresh()),
            ],
        ]);
    }

    /**
     * Audit trail immuable des changements de feature flags (MAT/BC-01 —
     * « audit des changements » du backlog deep-maturity) : une activation ou
     * désactivation de module est un changement d'accès, il doit être tracé.
     *
     * NB : les requêtes platform s'exécutent avec search_path=public (SET
     * explicite) alors que `audit_logs` vit dans le schéma tenant partagé —
     * l'INSERT est donc qualifié via tenantTable(), comme SectorTemplateService
     * / PlatformMetricsOverviewController. Sans cette qualification, l'écriture
     * échouait silencieusement (42P01 avalé par le try/catch).
     *
     * @param  array<string, bool>  $previousFeatures
     * @param  array<string, bool>  $newFeatures
     */
    private function auditFeatureChange(Request $request, Company $company, array $previousFeatures, array $newFeatures): void
    {
        try {
            DB::table($this->tenantTable('audit_logs'))->insert([
                'company_id' => $company->id,
                'user_id' => null,
                'action' => 'platform.company.features.update',
                'module' => 'platform',
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => json_encode($previousFeatures),
                'new_values' => json_encode($newFeatures),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'metadata' => json_encode(['actor' => (string) ($request->user()?->getAuthIdentifier() ?? 'system')]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Schéma tenant partagé qualifié (pattern repo — cf. SectorTemplateService).
     */
    private function tenantTable(string $table): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.'.$table : $table;
    }
}

