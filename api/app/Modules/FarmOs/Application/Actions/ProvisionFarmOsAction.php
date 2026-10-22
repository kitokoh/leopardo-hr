<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Application\Actions;

use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmActivity;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Models\FarmCapabilitySetting;
use App\Modules\FarmOs\Domain\Models\FarmOperation;
use App\Modules\FarmOs\Domain\Models\FarmSite;
use App\Modules\FarmOs\Domain\Models\Parcel;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityRecommender;

/**
 * Farm OS — Étape 4 de l'onboarding : PROVISIONNEMENT de l'espace d'exploitation.
 *
 * Cette action ne réinvente RIEN du socle (règle obligatoire de la mission) :
 *   - l'activation de la solution passe par le mécanisme existant
 *     {@see SolutionActivator} — modules requis du manifest, flag de solution,
 *     installation des permissions, audit `solution.activated` — aucun second
 *     système de tenants / rôles / permissions / provisioning n'est créé ;
 *   - les activités et les outils passent par les actions existantes du module
 *     ({@see SyncFarmActivitiesAction} / {@see UpdateFarmCapabilitiesAction}).
 *
 * IDEMPOTENT : rejouer l'étape 4 sur une exploitation déjà provisionnée ne
 * duplique rien et ne détruit rien — l'idempotence est fournie par les
 * mécanismes sous-jacents (`already_active` du SolutionActivator, upsert des
 * activités, update/`insertOrIgnore` des capacités).
 *
 * RÉCUPÉRABLE : si une étape échoue, un nouvel appel reprend exactement là où
 * l'échec a eu lieu — aucune opération partielle irréversible ; l'ordre place
 * délibérément l'opération la plus réversible en premier.
 *
 * Ne prétend JAMAIS avoir créé de données métier : le provisioning prépare
 * l'espace, la première parcelle reste un geste explicite de l'exploitant.
 */
final class ProvisionFarmOsAction
{
    public function __construct(
        private readonly SolutionActivator $activator,
        private readonly SyncFarmActivitiesAction $syncActivities,
        private readonly UpdateFarmCapabilitiesAction $updateCapabilities,
        private readonly FarmCapabilityRecommender $recommender,
    ) {}

    /**
     * @param  list<string>  $activities  Valeurs de {@see FarmActivity}
     * @param  array<string, bool>|null  $capabilityDecisions  Décisions explicites
     *                                                         (accepté / refusé / modifié) ; null ⇒ on applique la
     *                                                         PROPOSITION recalculée par le recommender.
     * @return array{
     *     status: string,
     *     solution: string,
     *     activities: list<string>,
     *     capabilities: array<string, bool>,
     *     enabled: list<string>,
     *     checklist: list<array{key: string, label: string, done: bool}>,
     *     next_actions: list<array{key: string, priority: int, capabilities: list<string>}>
     * }
     */
    public function execute(
        Company $company,
        array $activities,
        ?array $capabilityDecisions = null,
        ?int $actorId = null,
    ): array {
        // ── 1. Activité(s) déclarée(s) ────────────────────────────────────────
        // Upsert idempotent : une activité retirée est close (`ended_at`),
        // jamais supprimée — les données et l'historique restent intacts.
        $savedActivities = $this->syncActivities->execute($company, $this->resolveActivities($activities));

        // ── 2. Activation de la solution ──────────────────────────────────────
        // Mécanisme existant : active d'abord les modules REQUIS du manifest
        // (`rh`) puis la solution. `already_active` ⇒ rejeu sans effet.
        $activation = $this->activator->activateWithDependencies($company, 'farm_os', $actorId);

        // ── 3. Outils activés ─────────────────────────────────────────────────
        // Décisions explicites si l'utilisateur en a envoyé (un refus est
        // persisté et respecté), sinon la proposition recalculée. Farm OS
        // n'active jamais un outil dans le dos de l'exploitant.
        $decisions = $capabilityDecisions ?? $this->recommendedDecisions($savedActivities);
        $this->updateCapabilities->execute($company, $decisions, 'provisioning', $actorId);

        $enabled = $this->enabledCapabilities();

        return [
            'status' => $activation['status'],
            'solution' => 'farm_os',
            'activities' => $savedActivities,
            'capabilities' => $decisions,
            'enabled' => $enabled,
            // Checklist HONNÊTE : `done` reflète l'état réel relu en base,
            // jamais l'intention du provisionnement.
            'checklist' => $this->checklist($company, $savedActivities, $enabled),
            'next_actions' => $this->nextActions($enabled),
        ];
    }

    /**
     * Allowlist FAIL-CLOSED des activités déclarées : toute valeur absente du
     * registre fermé {@see FarmActivity} est écartée avant d'atteindre le
     * moindre effet de bord. Le contrôleur valide déjà la requête, mais l'action
     * reste appelable directement (tests, jobs, commandes) : la validation ne
     * doit jamais dépendre du seul appelant HTTP.
     *
     * @param  list<string>  $activities
     * @return list<string>
     */
    private function resolveActivities(array $activities): array
    {
        $resolved = [];

        foreach ($activities as $activity) {
            $enum = FarmActivity::tryFrom((string) $activity);

            if ($enum !== null) {
                $resolved[] = $enum->value;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Proposition d'outils transformée en décisions : seule la proposition est
     * activée, les autres outils sont explicitement refusés (`false`) afin que
     * l'exploitant puisse les activer lui-même plus tard sans qu'ils lui
     * soient imposés.
     *
     * @param  list<string>  $activities
     * @return array<string, bool>
     */
    private function recommendedDecisions(array $activities): array
    {
        $recommended = $this->recommender->forActivities($activities);
        $decisions = [];

        foreach (FarmCapability::values() as $capability) {
            $decisions[$capability] = in_array($capability, $recommended, true);
        }

        return $decisions;
    }

    /**
     * Capacités réellement actives, relues depuis les décisions persistées
     * (source unique, y compris les refus).
     *
     * @return list<string>
     */
    private function enabledCapabilities(): array
    {
        $enabled = [];

        foreach (FarmCapabilitySetting::decisions() as $capability => $isEnabled) {
            if ($isEnabled === true && FarmCapability::tryFrom((string) $capability) !== null) {
                $enabled[] = (string) $capability;
            }
        }

        return $enabled;
    }

    /**
     * Liste de contrôle des étapes restantes — état RÉEL, jamais optimiste.
     *
     * @param  list<string>  $activities
     * @param  list<string>  $enabled
     * @return list<array{key: string, label: string, done: bool}>
     */
    private function checklist(Company $company, array $activities, array $enabled): array
    {
        return [
            ['key' => 'farm_activity_declared', 'label' => __('farm.checklist.activity_declared'), 'done' => $activities !== []],
            ['key' => 'farm_tools_decided', 'label' => __('farm.checklist.tools_decided'), 'done' => FarmCapabilitySetting::decisions() !== []],
            ['key' => 'farm_solution_active', 'label' => __('farm.checklist.solution_active'), 'done' => $company->hasFeature('farm_os')],
            ['key' => 'farm_first_site', 'label' => __('farm.checklist.first_site'), 'done' => FarmSite::query()->exists()],
            ['key' => 'farm_first_parcel', 'label' => __('farm.checklist.first_parcel'), 'done' => Parcel::query()->exists()],
            ['key' => 'farm_first_operation', 'label' => __('farm.checklist.first_operation'), 'done' => FarmOperation::query()->exists()],
            // Honnêteté : la main-d'œuvre n'est « prête » que si l'outil est
            // réellement activé — un profil sans salariés ne la voit pas cochée.
            ['key' => 'farm_workers_ready', 'label' => __('farm.checklist.workers_ready'), 'done' => in_array(FarmCapability::Workers->value, $enabled, true)],
        ];
    }

    /**
     * Propose les actions SUIVANTES utiles compte tenu des outils réellement
     * activés (priorité croissante) : le nouvel utilisateur voit la suite du
     * parcours au lieu d'un écran vide.
     *
     * @param  list<string>  $enabled
     * @return list<array{key: string, priority: int, capabilities: list<string>}>
     */
    private function nextActions(array $enabled): array
    {
        $catalog = [
            ['key' => 'create_first_site', 'priority' => 1, 'capabilities' => [FarmCapability::ParcelsCrops->value]],
            ['key' => 'create_first_parcel', 'priority' => 2, 'capabilities' => [FarmCapability::ParcelsCrops->value]],
            ['key' => 'plan_first_operation', 'priority' => 3, 'capabilities' => [FarmCapability::WorkPlanning->value]],
            ['key' => 'register_input_stock', 'priority' => 4, 'capabilities' => [FarmCapability::StocksInputs->value]],
            ['key' => 'register_equipment', 'priority' => 5, 'capabilities' => [FarmCapability::Equipment->value]],
            ['key' => 'register_livestock_batch', 'priority' => 6, 'capabilities' => [FarmCapability::Livestock->value]],
            ['key' => 'invite_first_worker', 'priority' => 7, 'capabilities' => [FarmCapability::Workers->value]],
        ];

        $actions = [];

        foreach ($catalog as $action) {
            $required = $action['capabilities'];

            // Toutes les capacités requises doivent être réellement activées.
            if (array_values(array_intersect($required, $enabled)) !== $required) {
                continue;
            }

            $actions[] = $action;
        }

        return $actions;
    }
}
