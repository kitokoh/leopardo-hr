<?php

declare(strict_types=1);

namespace App\Shared\Onboarding;

use App\Shared\Contracts\Onboarding\StepPredicate;

/**
 * Registre des prédicats de complétion d'étapes d'onboarding (#9787).
 *
 * Même pattern d'inversion de dépendance que `DemoDataRegistry` et
 * `SolutionCatalogue` : les modules métier enregistrent leur prédicat depuis
 * leur provider (`resolving()`), le module `Onboarding` ne référence que ce
 * registre et le contrat partagé — jamais `App\Modules\*` (garde #5584).
 *
 * Fail-closed : une étape sans prédicat enregistré n'est PAS évaluée ici ;
 * `StepCompletionGuard` retombe alors sur ses prédicats internes, puis sur le
 * comportement « étape déclarative » (`null`) — jamais de complétion inventée.
 */
final class StepPredicateRegistry
{
    /** @var list<StepPredicate> */
    private array $predicates = [];

    public function register(StepPredicate $predicate): void
    {
        $this->predicates[] = $predicate;
    }

    /**
     * Résout le prédicat qui sait évaluer cette étape, ou `null` si aucun
     * module ne s'en déclare responsable.
     */
    public function resolve(string $stepKey): ?StepPredicate
    {
        foreach ($this->predicates as $predicate) {
            if ($predicate->supports($stepKey)) {
                return $predicate;
            }
        }

        return null;
    }

    /** Nombre de prédicats enregistrés (diagnostic / tests). */
    public function count(): int
    {
        return count($this->predicates);
    }
}
