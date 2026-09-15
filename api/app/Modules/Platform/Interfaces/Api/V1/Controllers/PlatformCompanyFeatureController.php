<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlagAuditRecorder;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\SolutionActivated;
use App\Http\Controllers\Controller;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlatformCompanyFeatureController extends Controller
{
    public function __construct(
        private readonly FeatureFlagAuditRecorder $auditRecorder,
    ) {}

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

        // #7432 — les features EFFECTIVES du tenant (registre résolu) servent
        // de valeur de repli quand une clé est absente du payload : un client
        // qui n'envoie pas `features.training` ne doit pas éteindre le module
        // par surprise (même garantie que pour les autres modules).
        $current = FeatureFlag::for($company);

        $features = [];
        foreach (Company::KNOWN_MODULES as $module) {
            $features[$module] = $module === 'rh'
                ? true
                : (bool) ($validated['features'][$module] ?? $current[$module] ?? false);
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

        // Audit 2026-09-14 — activer une VERTICALE depuis la console plateforme
        // ne faisait qu'écrire le flag : le module n'installait jamais ses
        // données d'amorçage. C'est pourtant LE chemin officiel d'activation
        // des verticales (elles sont volontairement hors périmètre de
        // l'auto-activation client, cf. `CompanyModuleController`). Concrètement,
        // une agence de voyage activée par un opérateur recevait la verticale
        // avec un référentiel géographique VIDE, donc aucun trajet créable.
        //
        // Les modules concernés écoutent `SolutionActivated` pour installer
        // leurs prérequis (idempotent). Fail-soft volontaire : l'activation du
        // flag reste acquise même si l'amorçage échoue — il est rejouable via
        // les commandes OPS (`leopardo:travel:activate`, `…:restaurant:activate`).
        foreach ($features as $module => $value) {
            if ($value !== true || ($before[$module] ?? false) === true) {
                continue;
            }

            try {
                // `$company` (non-null, issu de PlatformCompanyLookup::findOrFail)
                // et non `$company->fresh()` : ce dernier retourne `Company|null`
                // et fait échouer PHPStan Strict niveau 8 (`argument.type`) —
                // le gate exact qui bloque les PR de ce train.
                SolutionActivated::dispatch($company, $module);
            } catch (\Throwable $e) {
                Log::error('platform.features.solution_install_failed', [
                    'company_id' => $company->id,
                    'solution' => $module,
                    'error' => $e->getMessage(),
                ]);
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
