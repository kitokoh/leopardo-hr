<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
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

        $oldCrm = $company->hasFeature('crm');
        $company->features = $features;
        $company->save();
        $company->refresh();

        // #5742 (CRM PRE) : l'activation/désactivation du CRM est une mutation
        // sensible — journalisée (ADR-CRM-004 : préfixe crm.feature.*).
        if ($company->hasFeature('crm') !== $oldCrm) {
            // PlatformCompanyLookup a posé `SET search_path TO public` (la
            // table audit_logs est une table TENANT : shared_tenants en mode
            // shared) — restaurer le search_path du tenant avant l'écriture.
            DB::statement('SET search_path TO '.$company->getSafeSearchPath());

            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $request->user()?->id,
                'action' => $company->hasFeature('crm') ? 'crm.feature.enabled' : 'crm.feature.disabled',
                'module' => 'platform',
                'request_id' => $request->header('X-Request-Id'),
                // `auditable_id` est un bigint — les company id sont des UUID,
                // on porte la company via company_id (colonne dédiée) + metadata.
                'old_values' => ['crm' => $oldCrm],
                'new_values' => ['crm' => $company->hasFeature('crm')],
                'metadata' => ['company_id' => $company->id],
                'ip_address' => $request->ip(),
            ]);
        }

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company),
            ],
        ]);
    }
}

