<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Fundraising\Domain\Models\FundraisingSettings;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingSettingsResolver;
use App\Modules\Fundraising\Interfaces\Api\V1\Requests\UpdateFundraisingSettingsRequest;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\FundraisingSettingsResource;
use App\Support\PlatformCompanyLookup;
use Illuminate\Http\JsonResponse;

/**
 * #8441 — paramétrage FUNDRAISING **par l'admin plateforme**, dans le
 * contexte d'une entreprise cliente explicite.
 *
 * Le défaut corrigé : la console admin ouvre `FundraisingSettingsView` depuis
 * la fiche entreprise (`VERTICAL_SURFACES`, FUND-074), mais la vue appelait
 * `GET/PUT /fundraising/settings` — routes TENANT qui résolvent la compagnie
 * de la session Sanctum de l'admin. L'admin éditait donc les réglages de son
 * propre tenant de session, jamais ceux de l'entreprise consultée : le bouton
 * « paramétrer le module cagnottes » de la fiche entreprise était trompeur.
 *
 * Ces routes platform-scopées (`auth:super_admin_api` +
 * `platform.permission:companies.manage`, groupe routes/api.php) lisent et
 * écrivent la ligne `fundraising_settings` du tenant CIBLÉ, via
 * `TenantManager::withinTenant()` — même ressource et même validation que le
 * contrôleur tenant (aucun double contrat). Fail-closed : une entreprise
 * sans le flag `fundraising` reçoit un 403 explicite (activer d'abord le
 * module depuis la fiche entreprise).
 */
final class PlatformFundraisingSettingsController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly FundraisingSettingsResolver $resolver,
    ) {}

    /**
     * GET /platform/companies/{company}/fundraising/settings — settings du
     * tenant ciblé (persistés ou défauts, sans créer de ligne en lecture).
     */
    public function show(string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $this->guardFundraisingEnabled($target->hasFeature('fundraising'));

        return $this->tenantManager->withinTenant(
            $target,
            fn (): JsonResponse => (new FundraisingSettingsResource(
                $this->resolver->forCurrentCompany()
            ))->response(),
        );
    }

    /**
     * PUT /platform/companies/{company}/fundraising/settings — upsert de la
     * ligne unique du tenant ciblé (même payload que la route tenant).
     */
    public function update(UpdateFundraisingSettingsRequest $request, string $company): JsonResponse
    {
        $target = PlatformCompanyLookup::findOrFail($company);
        $this->guardFundraisingEnabled($target->hasFeature('fundraising'));

        return $this->tenantManager->withinTenant($target, function () use ($request): JsonResponse {
            /** @var FundraisingSettings $settings */
            $settings = FundraisingSettings::query()->first() ?? new FundraisingSettings;

            $settings->fill($this->payload($request->validated()));
            $settings->save();

            // Upsert idempotent → toujours 200 (miroir du contrôleur tenant).
            return (new FundraisingSettingsResource($settings->refresh()))
                ->response()
                ->setStatusCode(200);
        });
    }

    /**
     * Fail-closed : pas de paramétrage sur une entreprise où la verticale
     * n'est pas activée — écrire des réglages invisibles serait trompeur.
     */
    private function guardFundraisingEnabled(bool $enabled): void
    {
        abort_unless($enabled, 403, __('errors.FUNDRAISING_VERTICAL_NOT_ENABLED'));
    }

    /**
     * Miroir de FundraisingSettingsController::payload() — la duplication est
     * assumée pour garder les deux contrôleurs indépendants (un contrat par
     * surface : tenant vs plateforme).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'enabled_methods' => array_values(array_map('strval', $validated['enabled_methods'])),
            'default_min_amount' => $validated['default_min_amount'] ?? null,
            'default_max_amount' => $validated['default_max_amount'] ?? null,
            'default_suggested_amounts' => $validated['default_suggested_amounts'] ?? null,
            'allow_anonymous' => (bool) ($validated['allow_anonymous'] ?? true),
            'allow_messages' => (bool) ($validated['allow_messages'] ?? true),
            'show_supporters' => (bool) ($validated['show_supporters'] ?? true),
        ];
    }
}
