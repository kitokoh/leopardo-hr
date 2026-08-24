<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Application\Actions\AccountingSettingsDefaults;
use App\Modules\Accounting\Domain\Models\AccountingSettings;
use App\Modules\Accounting\Interfaces\Api\V1\Requests\UpdateAccountingSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paramétrage comptable par entreprise — issue #5232.
 *
 * RBAC (matrice comptabilité, COMPTABILITE_CONCEPTION.md §5) : `comptable`
 * (CRUD complet) et `principal` (paramétrage) — les routes portent le
 * middleware `api.manager:comptable,principal`.
 *
 * Isolation tenant : une ligne unique par entreprise (company_id unique) —
 * la ressource est résolue via la compagnie courante de la requête, jamais
 * par un id d'URL : aucune fuite cross-tenant possible (fail-closed #3727).
 */
class AccountingSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = $this->resolveSettings($request);

        return response()->json([
            'data' => $this->serialize($settings),
        ]);
    }

    public function update(UpdateAccountingSettingsRequest $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        /** @var AccountingSettings $settings */
        $settings = AccountingSettings::query()->updateOrCreate(
            ['company_id' => $companyId],
            $this->settingsPayload($request->validated()),
        );

        return response()->json([
            'data' => $this->serialize($settings->refresh()),
        ]);
    }

    /**
     * Retourne les settings persistés, ou les défauts dérivés du pays de
     * l'entreprise (CountryDefaults) si aucune ligne n'existe encore
     * (provisioning à la création d'entreprise non passé — ex. migration
     * tenant récente — ou entreprise préexistante).
     */
    private function resolveSettings(Request $request): AccountingSettings
    {
        $settings = AccountingSettings::query()
            ->where('company_id', $this->companyId($request))
            ->first();

        if ($settings !== null) {
            return $settings;
        }

        $settings = new AccountingSettings;
        $settings->company_id = $this->companyId($request);
        $settings->forceFill(AccountingSettingsDefaults::for($this->companyCountry()));

        return $settings;
    }

    /**
     * Filtre le payload validé sur les colonnes fillable du modèle.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function settingsPayload(array $validated): array
    {
        $allowed = [
            'currency',
            'document_language',
            'template_style',
            'payment_terms',
            'legal_mentions',
            'tva_rates',
            'number_series',
        ];

        return array_intersect_key($validated, array_flip($allowed));
    }

    private function companyId(Request $request): string
    {
        // getAttribute() : compagnie de l'employé authentifié (même pattern
        // que AccountingContactController — jamais de fuite cross-tenant).
        $companyId = $request->user()?->getAttribute('company_id');

        if (! is_string($companyId) || $companyId === '') {
            abort(403, 'Tenant context missing.');
        }

        return $companyId;
    }

    private function companyCountry(): ?string
    {
        if (! app()->bound('current_company')) {
            return null;
        }

        return currentCompany()->country ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AccountingSettings $settings): array
    {
        return [
            'currency' => $settings->currency,
            'document_language' => $settings->document_language,
            'template_style' => $settings->template_style,
            'payment_terms' => $settings->payment_terms,
            'legal_mentions' => $settings->legal_mentions,
            'tva_rates' => $settings->tva_rates ?? [],
            'number_series' => $settings->number_series ?? [],
            'updated_at' => $settings->updated_at?->toISOString(),
        ];
    }
}
