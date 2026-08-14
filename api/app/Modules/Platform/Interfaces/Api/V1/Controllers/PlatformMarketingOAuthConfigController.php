<?php

declare(strict_types=1);

namespace App\Modules\Platform\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Configuration OAuth marketing (providers sociaux) — contrat SPA admin,
 * issue #1764 : PUT /v1/platform/marketing/oauth-config appelé par
 * MarketingOAuthView sans exister côté API.
 *
 * Le client_secret est chiffré au repos (Crypt) ; il n'est jamais renvoyé
 * par GET (champ vide).
 */
class PlatformMarketingOAuthConfigController extends Controller
{
    /** @var array<int, string> */
    private const PROVIDERS = ['linkedin', 'facebook', 'twitter'];

    public function index(): JsonResponse
    {
        $configs = [];
        try {
            $rows = DB::table('platform_oauth_configs')->get(['provider', 'client_id', 'redirect_uri', 'client_secret_encrypted', 'updated_at']);
            foreach ($rows as $row) {
                $configs[$row->provider] = [
                    'provider' => $row->provider,
                    'client_id' => $row->client_id,
                    'redirect_uri' => $row->redirect_uri,
                    'has_client_secret' => $row->client_secret_encrypted !== null,
                    'updated_at' => $row->updated_at,
                ];
            }
        } catch (\Throwable) {
            // table absente (env partiel) → configs vides
        }

        return response()->json(['data' => $configs]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', self::PROVIDERS)],
            'client_id' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:500'],
            'client_secret' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $existing = DB::table('platform_oauth_configs')->where('provider', $validated['provider'])->first();

            DB::table('platform_oauth_configs')->updateOrInsert(
                ['provider' => $validated['provider']],
                [
                    'client_id' => $validated['client_id'],
                    'redirect_uri' => $validated['redirect_uri'],
                    // Préserver le secret existant si aucun nouveau n'est fourni.
                    'client_secret_encrypted' => isset($validated['client_secret'])
                        ? Crypt::encryptString($validated['client_secret'])
                        : ($existing->client_secret_encrypted ?? null),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'OAUTH_CONFIG_SAVE_FAILED', 'message' => __('platform.oauth_save_failed')], 500);
        }

        return response()->json(['status' => 'saved']);
    }
}
