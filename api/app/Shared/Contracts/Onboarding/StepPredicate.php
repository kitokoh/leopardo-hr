<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Onboarding;

/**
 * Prédicat de complétion d'une étape d'onboarding (#9787).
 *
 * Une étape d'onboarding n'est « terminée » que si une ACTION RÉELLE a eu lieu
 * côté serveur (cf. #7261). Or le module `Onboarding` ne peut pas importer les
 * modules métier (garde d'isolation #5584) : il ne sait donc pas vérifier une
 * étape propre à une verticale (ex. `edu_setup_campus`).
 *
 * Ce contrat inverse la dépendance : chaque module métier implémente le
 * prédicat de SES étapes et l'enregistre dans `StepPredicateRegistry` depuis
 * son provider. `Onboarding` ne connaît que le contrat partagé.
 */
interface StepPredicate
{
    /** Vrai si ce prédicat sait évaluer l'étape donnée. */
    public function supports(string $stepKey): bool;

    /**
     * Vrai si l'action réelle correspondant à l'étape a été constatée pour
     * cette société. N'est appelé que si `supports()` a répondu vrai.
     */
    public function isSatisfied(string $stepKey, string $companyId): bool;
}
