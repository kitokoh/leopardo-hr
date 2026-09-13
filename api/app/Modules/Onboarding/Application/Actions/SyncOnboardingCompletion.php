<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Infrastructure\Services\CompanyOnboardingCompletionWriter;

/**
 * Use Case: persiste la fin d'onboarding côté serveur (#7262).
 *
 * Le flag « onboarding terminé » vivait uniquement dans le `localStorage` du
 * navigateur (`OnboardingWizard::completeLocalOnboarding`) : l'assistant se
 * rouvrait donc sur un autre appareil (ou après nettoyage du stockage), et le
 * back-office/analytics ne pouvait pas savoir quels tenants avaient réellement
 * terminé leur parcours. Le serveur devient la source de vérité.
 *
 * Définition retenue : l'onboarding est terminé quand TOUTES les étapes de la
 * société sont `completed` ou `skipped` — exactement la progression exposée par
 * `OnboardingStepController::progress()` et le moteur calculé
 * (`OnboardingChecklistController`). Le portail lit le résultat via
 * `company.metadata.onboarding_completed`, déjà renvoyé par `/auth/me`
 * (`EmployeeResource`, #7235).
 *
 * L'écriture vise `public.companies` (registre des sociétés, hors schémas de
 * tenant) : requête qualifiée, même pattern que
 * `CompanyBrandingController::persistMetadata`, pour que le `search_path` du
 * tenant ne détourne pas l'écriture vers un schéma de tenant.
 *
 * Idempotent : la liste des étapes et le flag déjà posé sont vérifiés avant
 * écriture, la date de fin n'est jamais réécrite.
 */
final class SyncOnboardingCompletion
{
    public function __construct(
        private readonly CompanyOnboardingCompletionWriter $writer,
    ) {}

    public function execute(string $companyId): void
    {
        $steps = OnboardingStep::where('company_id', $companyId)->get();

        if ($steps->isEmpty()) {
            return;
        }

        $finished = $steps->every(
            fn (OnboardingStep $step): bool => in_array($step->status, ['completed', 'skipped'], true)
        );

        if (! $finished) {
            return;
        }

        $company = Company::find($companyId);

        if ($company === null) {
            return;
        }

        $metadata = $company->metadata ?? [];

        if (($metadata['onboarding_completed'] ?? null) === true) {
            return;
        }

        $metadata['onboarding_completed'] = true;
        $metadata['onboarding_completed_at'] = now()->toIso8601String();

        $this->writer->persist($companyId, $metadata);
    }
}
