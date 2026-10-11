<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fundraising\Domain\Models\FundraisingSettings;
use App\Modules\Fundraising\Infrastructure\Services\FundraisingSettingsResolver;
use App\Modules\Fundraising\Interfaces\Api\V1\Requests\UpdateFundraisingSettingsRequest;
use App\Modules\Fundraising\Interfaces\Api\V1\Resources\FundraisingSettingsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paramétrage de la verticale FUNDRAISING (cagnottes solidaires) — spec
 * SOLUTION_FUNDRAISING.md §6 : moyens de paiement offerts, garde-fous par
 * défaut, montants suggérés, options de la surface publique.
 *
 * RBAC : groupe `api.manager:principal,rh` + middleware `module.fundraising`
 * (fail-closed) sur les routes.
 *
 * Isolation tenant : une ligne unique par entreprise, résolue via la
 * compagnie courante de la requête (pattern `AccountingSettingsController`
 * #5232) — jamais par un id d'URL : aucune fuite cross-tenant possible.
 */
final class FundraisingSettingsController extends Controller
{
    public function __construct(
        private readonly FundraisingSettingsResolver $resolver,
    ) {}

    /**
     * GET /fundraising/settings — settings persistés ou défauts (aucune
     * ligne créée tant que le responsable n'a jamais enregistré).
     */
    public function show(Request $request): JsonResponse
    {
        return (new FundraisingSettingsResource(
            $this->resolver->forCurrentCompany()
        ))->response();
    }

    /**
     * PUT /fundraising/settings — upsert de la ligne unique du tenant.
     */
    public function update(UpdateFundraisingSettingsRequest $request): JsonResponse
    {
        /** @var FundraisingSettings $settings */
        $settings = FundraisingSettings::query()->first() ?? new FundraisingSettings;

        $settings->fill($this->payload($request->validated()));
        $settings->save();

        // Upsert idempotent → toujours 200 (pattern AccountingSettingsController
        // #5232) : sans statut explicite, JsonResource sert 201 quand le modèle
        // enveloppé vient d'être créé (wasRecentlyCreated au premier PUT).
        return (new FundraisingSettingsResource($settings->refresh()))
            ->response()
            ->setStatusCode(200);
    }

    /**
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
