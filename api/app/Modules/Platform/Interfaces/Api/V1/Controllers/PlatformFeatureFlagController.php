<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Core\Feature\Domain\Models\PlatformFeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * MAT-010 (#5868) — Kill switches / feature flags plateforme (BC-01 PLATFORM).
 *
 * Administration super-admin des interrupteurs plateforme :
 *   GET  /api/v1/platform/feature-flags            → liste (avec audit)
 *   POST /api/v1/platform/feature-flags            → créer/mettre à jour
 *
 * Toute écriture est auditée (historique append-only de la ligne) et
 * l'état par défaut est fail-closed.
 */
class PlatformFeatureFlagController extends Controller
{
    public function index(): JsonResponse
    {
        $flags = FeatureFlag::allFlags()->map(fn (PlatformFeatureFlag $flag): array => [
            'flag_key' => $flag->flag_key,
            'dimension' => $flag->dimension,
            'dimension_value' => $flag->dimension_value,
            'enabled' => (bool) $flag->enabled,
            'reason' => $flag->reason,
            'changed_by' => $flag->changed_by,
            'history' => $flag->history,
            'updated_at' => $flag->updated_at?->toIso8601String(),
        ]);

        return new JsonResponse(['data' => $flags->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flag_key' => ['required', 'string', 'max:120'],
            'dimension' => ['required', 'string', 'in:'.implode(',', PlatformFeatureFlag::DIMENSIONS)],
            'dimension_value' => ['nullable', 'string', 'max:120'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = (string) ($request->user()?->email ?? $request->user()?->getAuthIdentifier() ?? 'platform-api');

        $flag = FeatureFlag::setFlag(
            (string) $validated['flag_key'],
            (string) $validated['dimension'],
            isset($validated['dimension_value']) && $validated['dimension_value'] !== null ? (string) $validated['dimension_value'] : null,
            (bool) $validated['enabled'],
            $validated['reason'] ?? null,
            $actor,
        );

        Log::info('platform.feature_flag.api.set', [
            'flag_key' => $flag->flag_key,
            'dimension' => $flag->dimension,
            'dimension_value' => $flag->dimension_value,
            'enabled' => $flag->enabled,
            'actor' => $actor,
        ]);

        return new JsonResponse([
            'data' => [
                'flag_key' => $flag->flag_key,
                'dimension' => $flag->dimension,
                'dimension_value' => $flag->dimension_value,
                'enabled' => (bool) $flag->enabled,
                'reason' => $flag->reason,
                'changed_by' => $flag->changed_by,
                'history' => $flag->history,
            ],
        ], 201);
    }
}
