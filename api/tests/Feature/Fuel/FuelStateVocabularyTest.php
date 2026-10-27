<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Modules\FuelStation\Domain\Models\FuelIncident;
use App\Modules\FuelStation\Domain\Models\FuelMaintenanceTask;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * #8578 — verrouiller le vocabulaire d'états canonique.
 *
 * Ces tests ne testent pas un comportement : ils testent la **cohérence du
 * vocabulaire lui-même**. C'est nécessaire parce que le défaut d'origine
 * n'était pas un bug de logique mais une divergence entre quatre sources de
 * vérité (défaut SQL, défaut du modèle, graphe de transitions, listes de
 * constantes). Aucun test fonctionnel ne pouvait l'attraper : chaque pièce
 * prise isolément était « cohérente ».
 *
 * Sans ce garde-fou, la prochaine personne qui ajoute un état le fera dans une
 * liste et pas dans l'autre — et les 9 tests rouges reviendront sous une autre
 * forme.
 */
class FuelStateVocabularyTest extends TestCase
{
    /**
     * Aucune constante d'état ne doit être un synonyme d'une autre.
     *
     * `open`, `pending`, `todo` décrivaient le même fait avec trois mots ; c'est
     * ce doublon qui a rendu le graphe des tâches mort (`transitionTask` lisait
     * `STATUS_OPEN`, que rien n'écrit jamais).
     */
    public function test_incident_statuses_are_unique_and_cover_the_graph(): void
    {
        $statuses = FuelIncident::STATUSES;

        self::assertSame(
            $statuses,
            array_values(array_unique($statuses)),
            'STATUSES contient un doublon',
        );

        // Tout état exposé doit être atteignable ou être l'état de naissance.
        foreach ($statuses as $status) {
            $reachable = $status === FuelIncident::DEFAULT_STATUS;

            foreach (FuelIncident::TRANSITIONS as $targets) {
                if (in_array($status, $targets, true)) {
                    $reachable = true;
                    break;
                }
            }

            self::assertTrue(
                $reachable,
                "l'état « {$status} » est exposé mais n'est atteignable par aucune transition",
            );
        }

        // Et tout état du graphe doit être exposé (sinon il est invisible en API).
        foreach (array_keys(FuelIncident::TRANSITIONS) as $from) {
            self::assertContains($from, $statuses, "l'état de départ « {$from} » n'est pas exposé");
        }
    }

    /**
     * `TRANSITION_TARGETS` doit être exactement l'union des cibles du graphe.
     *
     * Une constante PHP ne peut pas être calculée depuis une autre, donc la
     * liste est recopiée — et une liste recopiée dérive. C'était le cas :
     * `assigned` manquait, ce qui rendait l'état inatteignable par l'endpoint
     * de transition alors qu'il existait dans le graphe.
     */
    public function test_transition_targets_match_the_graph(): void
    {
        $fromGraph = [];
        foreach (FuelIncident::TRANSITIONS as $targets) {
            foreach ($targets as $target) {
                $fromGraph[$target] = true;
            }
        }
        $fromGraph = array_keys($fromGraph);
        sort($fromGraph);

        $declared = FuelIncident::TRANSITION_TARGETS;
        sort($declared);

        self::assertSame($fromGraph, $declared, 'TRANSITION_TARGETS a dérivé du graphe');
    }

    /** Le graphe ne doit contenir aucune arête vers un état inexistant. */
    public function test_graph_has_no_edge_to_an_unknown_state(): void
    {
        foreach (FuelIncident::TRANSITIONS as $from => $targets) {
            self::assertContains($from, FuelIncident::STATUSES, "état de départ inconnu : {$from}");

            foreach ($targets as $target) {
                self::assertContains($target, FuelIncident::STATUSES, "cible inconnue : {$from} → {$target}");
            }
        }
    }

    /**
     * D3 — on ne clôture jamais sans résolution.
     *
     * C'est la décision structurante de #8578 : `closed` signifie « réglé et
     * classé », pas « abandonné ». Le test la fige pour qu'une future
     * « simplification » du graphe ne la supprime pas par inadvertance.
     */
    #[DataProvider('nonClosableStates')]
    public function test_only_resolved_leads_to_closed(string $state): void
    {
        self::assertNotContains(
            FuelIncident::STATUS_CLOSED,
            FuelIncident::TRANSITIONS[$state],
            "« {$state} » ne doit pas pouvoir clôturer sans résolution",
        );
    }

    /** @return array<string, array{string}> */
    public static function nonClosableStates(): array
    {
        return [
            'reported' => [FuelIncident::STATUS_REPORTED],
            'assigned' => [FuelIncident::STATUS_ASSIGNED],
            'in_progress' => [FuelIncident::STATUS_IN_PROGRESS],
        ];
    }

    /** D1 — un seul état de naissance, et il a des transitions sortantes. */
    public function test_birth_state_has_outgoing_transitions(): void
    {
        self::assertSame(FuelIncident::STATUS_REPORTED, FuelIncident::DEFAULT_STATUS);
        self::assertNotEmpty(
            FuelIncident::TRANSITIONS[FuelIncident::DEFAULT_STATUS],
            "l'état de naissance n'a aucune transition sortante : l'incident serait immuable",
        );
    }

    /** D4 — la tâche naît `todo` et ce n'est pas un synonyme d'autre chose. */
    public function test_task_birth_state_is_todo_and_can_transition(): void
    {
        self::assertSame(FuelMaintenanceTask::STATUS_TODO, FuelMaintenanceTask::DEFAULT_STATUS);

        self::assertSame(
            [FuelMaintenanceTask::STATUS_TODO, FuelMaintenanceTask::STATUS_IN_PROGRESS, FuelMaintenanceTask::STATUS_DONE, FuelMaintenanceTask::STATUS_CANCELLED],
            FuelMaintenanceTask::STATUSES,
            'STATUSES de tâche doit contenir exactement les quatre états canoniques, sans synonyme',
        );

        // Les synonymes supprimés ne doivent pas revenir.
        foreach (['open', 'pending'] as $removed) {
            self::assertNotContains(
                $removed,
                FuelMaintenanceTask::STATUSES,
                "« {$removed} » est un synonyme supprimé de `todo` : il ne doit pas revenir",
            );
        }
    }
}
