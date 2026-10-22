<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Application\Actions\ProvisionFarmOsAction;
use App\Modules\FarmOs\Application\Actions\SyncFarmActivitiesAction;
use App\Modules\FarmOs\Application\Actions\UpdateFarmCapabilitiesAction;
use App\Modules\FarmOs\Domain\Enums\AreaUnit;
use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmOperationStatus;
use App\Modules\FarmOs\Domain\Enums\FarmOperationType;
use App\Modules\FarmOs\Domain\Models\FarmActivityRecord;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityRecommender;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Farm OS — Réglages de l'exploitation : activités déclarées, outils activés,
 * profil d'exploitation et états honnêtes (capacités réellement actives).
 *
 * Toutes les décisions d'outils sont persistées (y compris les REFUS) et
 * relues par le front : jamais de faux état « activé » côté client.
 */
final class FarmSettingsController
{
    public function __construct(
        private readonly FarmCapabilityGate $gate,
        private readonly FarmCapabilityRecommender $recommender,
        private readonly SyncFarmActivitiesAction $syncActivities,
        private readonly UpdateFarmCapabilitiesAction $updateCapabilities,
        private readonly ProvisionFarmOsAction $provisionFarm,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->company();

        return response()->json([
            'data' => [
                'solution' => 'farm_os',
                'activities' => $this->activityPayload(),
                'capabilities' => [
                    'enabled' => $this->gate->enabled($company),
                    'decisions' => $this->gate->decisions(),
                    'available' => FarmCapability::values(),
                ],
                'reference' => [
                    'operation_types' => FarmOperationType::values(),
                    'operation_statuses' => FarmOperationStatus::values(),
                    'area_units' => array_map(
                        static fn (AreaUnit $unit): string => $unit->value,
                        AreaUnit::cases()
                    ),
                ],
            ],
        ]);
    }

    /**
     * Étape 1 — activités (sélection multiple). Fail-closed : toute valeur hors
     * du registre fermé {@see FarmActivity} est rejetée.
     */
    public function updateActivities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activities' => ['required', 'array', 'min:1'],
            'activities.*' => ['string', Rule::in(FarmActivity::values())],
        ]);

        /** @var list<string> $activities */
        $activities = array_values($validated['activities']);
        $saved = $this->syncActivities->execute($this->company(), $activities);

        return response()->json([
            'data' => [
                'activities' => $this->activityPayload(),
                'saved' => $saved,
                'recommended' => $this->recommender->forActivities($saved),
                'optional' => $this->recommender->optionalForActivities($saved),
            ],
        ]);
    }

    /**
     * Étape 4 — provisioning de l'espace d'exploitation.
     *
     * IDEMPOTENT (un rejeu ne duplique ni ne détruit rien) et RÉCUPÉRABLE (un
     * nouvel appel reprend l'étape échouée). Réutilise le mécanisme existant
     * de Leopardo Business OS (SolutionActivator : modules requis, flag de
     * solution, permissions, audit) — Farm OS ne crée aucun second
     * provisioning.
     */
    public function provision(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activities' => ['required', 'array', 'min:1'],
            'activities.*' => ['string', Rule::in(FarmActivity::values())],
            'capabilities' => ['sometimes', 'array'],
            'capabilities.*' => ['boolean'],
        ]);

        /** @var array<string, bool> $decisions */
        $decisions = $validated['capabilities'] ?? [];
        // Allowlist fail-closed : une clé inconnue est ignorée, jamais
        // persistée. Aucune décision ⇒ le recommender décide (comportement null).
        $allowed = array_intersect_key($decisions, array_flip(FarmCapability::values()));

        $result = $this->provisionFarm->execute(
            $this->company(),
            array_values($validated['activities']),
            $allowed === [] ? null : $allowed,
            $request->user()?->id,
        );

        return response()->json(['data' => $result]);
    }

    /**
     * Étape 3 — outils recommandés puis décidés par l'utilisateur.
     */
    public function availableCapabilities(Request $request): JsonResponse
    {
        $activities = FarmActivityRecord::activeValues();

        return response()->json([
            'data' => [
                'recommended' => $this->recommender->forActivities($activities),
                'optional' => $this->recommender->optionalForActivities($activities),
                'enabled' => $this->gate->enabled($this->company()),
            ],
        ]);
    }

    public function updateCapabilities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'capabilities' => ['required', 'array'],
            'capabilities.*' => ['boolean'],
        ]);

        /** @var array<string, bool> $decisions */
        $decisions = $validated['capabilities'];
        $allowed = array_intersect_key($decisions, array_flip(FarmCapability::values()));

        if ($allowed === []) {
            return response()->json(['error' => 'NO_VALID_CAPABILITY', 'message' => 'NO_VALID_CAPABILITY'], 422);
        }

        $this->updateCapabilities->execute($this->company(), $allowed, 'settings', $request->user()?->id);

        return response()->json([
            'data' => [
                'enabled' => $this->gate->enabled($this->company()),
                'decisions' => $this->gate->decisions(),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityPayload(): array
    {
        $payload = [];

        foreach (FarmActivityRecord::query()->orderBy('id')->get() as $record) {
            $payload[] = [
                'activity' => $record->activity,
                'is_primary' => (bool) $record->is_primary,
                'declared_at' => $record->declared_at->toIso8601String(),
                'ended_at' => $record->ended_at?->toIso8601String(),
            ];
        }

        return $payload;
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
