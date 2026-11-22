<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Infrastructure\Services;

use App\Modules\EduManager\Domain\Models\EduCampus;
use App\Modules\EduManager\Domain\Models\EduClass;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Shared\Contracts\Onboarding\StepPredicate;

/**
 * Prédicats de complétion des étapes d'onboarding de la verticale école (#9787).
 *
 * Les trois étapes métier générées par `SeedDefaultSteps::personalizedSteps()`
 * quand `hasFeature('edumanager')` étaient DÉCLARATIVES : le bouton « Suivant »
 * les marquait terminées sans qu'aucune action réelle n'ait eu lieu, ce qui
 * faussait `progress_percent` et `go_live_ready` (même défaut que #7261).
 *
 * Ce prédicat fournit le signal serveur réel, sans que `Modules/Onboarding`
 * n'importe `Modules/EduManager` (garde d'isolation #5584) : il est enregistré
 * dans `StepPredicateRegistry` depuis `EduManagerServiceProvider`.
 *
 * Chaque requête filtre explicitement sur `company_id` : un tenant ne peut
 * jamais valider son étape grâce aux données d'un autre.
 */
final class EduOnboardingStepPredicate implements StepPredicate
{
    /** Étape « créer votre campus et votre année scolaire ». */
    public const STEP_CAMPUS = 'edu_setup_campus';

    /** Étape « créer vos classes ». */
    public const STEP_CLASSES = 'edu_setup_classes';

    /** Étape « inscrire vos premiers élèves ». */
    public const STEP_STUDENTS = 'edu_first_students';

    /** @var list<string> */
    private const SUPPORTED = [
        self::STEP_CAMPUS,
        self::STEP_CLASSES,
        self::STEP_STUDENTS,
    ];

    public function supports(string $stepKey): bool
    {
        return in_array($stepKey, self::SUPPORTED, true);
    }

    public function isSatisfied(string $stepKey, string $companyId): bool
    {
        return match ($stepKey) {
            self::STEP_CAMPUS => EduCampus::query()
                ->where('company_id', $companyId)
                ->where('status', EduCampus::STATUS_ACTIVE)
                ->exists(),

            self::STEP_CLASSES => EduClass::query()
                ->where('company_id', $companyId)
                ->exists(),

            self::STEP_STUDENTS => EduStudent::query()
                ->where('company_id', $companyId)
                ->where('status', EduStudent::STATUS_ACTIVE)
                ->exists(),

            default => false,
        };
    }
}
