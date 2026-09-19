<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Interfaces\Api\V1\Requests\UpdateRetailOnlineSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reglages de la boutique en ligne Leopardo Marche (BC-17 RETAIL, #7807).
 *
 * deny-by-default (RetailOnlineSettingsPolicy) : lecture membres du tenant,
 * gestion (activation, create-or-update) reservee principal/rh. Une seule
 * ligne par tenant — le PUT cree la ligne si absente puis la met a jour
 * (version++ a chaque ecriture, verrou optimiste).
 */
class RetailOnlineSettingsController extends Controller
{
    /**
     * GET /retail/online/settings — reglages courants (null si la boutique
     * n'a jamais ete configuree).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', RetailOnlineSettings::class);

        /** @var RetailOnlineSettings|null $settings */
        $settings = RetailOnlineSettings::query()
            ->where('company_id', $actor->company_id)
            ->first();

        return response()->json([
            'data' => $settings instanceof RetailOnlineSettings ? $this->payload($settings) : null,
        ]);
    }

    /**
     * PUT /retail/online/settings — create-or-update (principal/rh).
     */
    public function update(UpdateRetailOnlineSettingsRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var RetailOnlineSettings|null $settings */
        $settings = RetailOnlineSettings::query()
            ->where('company_id', $actor->company_id)
            ->first();

        if ($settings instanceof RetailOnlineSettings) {
            $this->authorize('update', $settings);

            $settings->update([
                'enabled' => $request->boolean('enabled', $settings->enabled),
                'shop_name' => $request->input('shop_name'),
                'shop_description' => $request->input('shop_description'),
                'city' => $request->input('city'),
                'contact_phone' => $request->input('contact_phone'),
                'contact_email' => $request->input('contact_email'),
                'currency' => $request->input('currency') ?? $settings->currency,
                'version' => $settings->version + 1,
            ]);
        } else {
            $this->authorize('create', RetailOnlineSettings::class);

            $settings = RetailOnlineSettings::query()->create([
                'company_id' => $actor->company_id,
                'enabled' => $request->boolean('enabled', false),
                'shop_name' => $request->input('shop_name'),
                'shop_description' => $request->input('shop_description'),
                'city' => $request->input('city'),
                'contact_phone' => $request->input('contact_phone'),
                'contact_email' => $request->input('contact_email'),
                'currency' => $request->input('currency', 'DZD'),
                'version' => 1,
            ]);
        }

        return response()->json(['data' => $this->payload($settings->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(RetailOnlineSettings $settings): array
    {
        return [
            'id' => $settings->id,
            'company_id' => $settings->company_id,
            'enabled' => $settings->enabled,
            'shop_name' => $settings->shop_name,
            'shop_description' => $settings->shop_description,
            'city' => $settings->city,
            'contact_phone' => $settings->contact_phone,
            'contact_email' => $settings->contact_email,
            'currency' => $settings->currency,
            'version' => $settings->version,
            'created_at' => $settings->created_at?->toIso8601String(),
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ];
    }
}
