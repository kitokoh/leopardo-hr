<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlagAuditRecorder;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformCompanyFeatureController extends Controller
{
    public function __construct(
        private readonly FeatureFlagAuditRecorder $auditRecorder,
    ) {
    }

    public function show(string $companyId): JsonResponse
    {
        $company = PlatformCompanyLookup::findOrFail($companyId);

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company),
                'known_modules' => Company::KNOWN_MODULES,
                'registry_version' => FeatureFlag::version(),
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

        $before = $company->features ?? [];

        $features = [];
        foreach (Company::KNOWN_MODULES as $module) {
            $features[$module] = $module === 'rh'
                ? true
                : (bool) ($validated['features'][$module] ?? false);
        }

        $company->features = $features;
        $company->save();

        // MAT-010 (#5868) — audit des bascules (avant/après par flag), dans le
        // contexte public posé par PlatformCompanyLookup.
        $actorUserId = $request->user()?->getAuthIdentifier() !== null
            ? (int) $request->user()->getAuthIdentifier()
            : null;

        foreach ($features as $module => $value) {
            $previous = (bool) ($before[$module] ?? ($module === 'rh'));

            if ($previous !== $value) {
                $this->auditRecorder->record(
                    companyId: $company->id,
                    flagKey: $module,
                    previousValue: $previous,
                    newValue: $value,
                    source: 'platform_controller',
                    actorUserId: $actorUserId,
                );
            }
        }

        return new JsonResponse([
            'data' => [
                'company_id' => $company->id,
                'features' => FeatureFlag::for($company->fresh()),
                'known_modules' => Company::KNOWN_MODULES,
                'registry_version' => FeatureFlag::version(),
            ],
        ]);
    }
}
