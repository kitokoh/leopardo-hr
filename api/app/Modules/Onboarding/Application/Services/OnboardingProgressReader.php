<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Services;

use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Support\Collection;

/**
 * #7300 — LECTURE CANONIQUE de la progression d'onboarding.
 *
 * Contexte (issue #7300) : trois surfaces exposaient trois progressions
 * différentes pour le MÊME tenant, au même instant (client 100 % / moteur
 * calculé 38 % / back-office admin 40 %). Cause racine : trois modèles
 * coexistaient sans source de vérité désignée.
 *
 * Décision (déjà écrite dans
 * `docs/dossierdeConception/11_ux_wireframes/24_ONBOARDING_GUIDE.md`, section
 * « API — Endpoints onboarding ») : la table `onboarding_steps` — seedée par
 * `SeedDefaultSteps`, complétée/sautée par l'utilisateur via
 * `/onboarding-setup/*` — est la **source de vérité unique**. Ce lecteur en est
 * la seule implémentation : `OnboardingStepController` (checklist + progress) et
 * le back-office plateforme l'utilisent au lieu de recalculer chacun la leur.
 *
 * Sémantique (inchangée, elle était déjà correcte) :
 *   - `completed_steps` compte les étapes `completed` **et** `skipped` ;
 *   - `progress_percent` = completed / total (0 si aucune étape) ;
 *   - `go_live_ready` exige que TOUTES les étapes `required` soient `completed`
 *     (une étape requise sautée n'ouvre pas le go-live).
 *
 * Ce lecteur ne seede rien : l'amorçage paresseux reste une décision du
 * contrôleur (elle écrit en base). Un tenant sans étape renvoie 0/0 proprement.
 */
final class OnboardingProgressReader
{
    /**
     * Étape considérée comme « faite » par la progression affichée au client.
     * NOTE : `go_live_ready` est plus strict — il n'accepte que `completed`.
     *
     * @var list<string>
     */
    private const DONE_STATUSES = ['completed', 'skipped'];

    /**
     * Progression canonique d'une société, lue depuis `onboarding_steps`.
     *
     * `initialized` distingue « 0 % parce que rien n'est fait » de « aucune
     * étape seedée » : un consommateur ne doit pas afficher 0 % (donc « mauvais
     * élève ») pour une société dont la checklist n'a jamais été amorcée.
     * L'amorçage reste à la charge de l'appelant (`SeedDefaultSteps`) — ce
     * lecteur est aussi utilisé par le portefeuille admin, qui parcourt toutes
     * les sociétés et ne doit surtout pas écrire pour chacune (#7302).
     *
     * @return array{
     *     initialized: bool,
     *     completed_steps: int,
     *     total_steps: int,
     *     progress_percent: int,
     *     progress: int,
     *     go_live_ready: bool,
     *     next_actions: list<array{key: string, label: string}>,
     *     steps: Collection<int, OnboardingStep>
     * }
     */
    public function read(string $companyId): array
    {
        /** @var Collection<int, OnboardingStep> $steps */
        $steps = OnboardingStep::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get();

        return $this->summarize($steps);
    }

    /**
     * Calcule la progression canonique à partir d'une collection déjà chargée.
     *
     * Exposé pour éviter une seconde requête quand l'appelant détient déjà les
     * étapes (et pour rendre la règle testable sans base).
     *
     * @param  Collection<int, OnboardingStep>  $steps
     * @return array{
     *     initialized: bool,
     *     completed_steps: int,
     *     total_steps: int,
     *     progress_percent: int,
     *     progress: int,
     *     go_live_ready: bool,
     *     next_actions: list<array{key: string, label: string}>,
     *     steps: Collection<int, OnboardingStep>
     * }
     */
    public function summarize(Collection $steps): array
    {
        $total = $steps->count();
        $completed = $steps->whereIn('status', self::DONE_STATUSES)->count();
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $allRequiredDone = $steps
            ->where('required', true)
            ->every(static fn (OnboardingStep $step): bool => $step->status === 'completed');

        /** @var list<array{key: string, label: string}> $nextActions */
        $nextActions = $steps
            ->where('status', 'pending')
            ->take(3)
            ->map(static fn (OnboardingStep $step): array => [
                'key' => (string) $step->step_key,
                'label' => (string) $step->title,
            ])
            ->values()
            ->all();

        return [
            'initialized' => $total > 0,
            'completed_steps' => $completed,
            'total_steps' => $total,
            'progress_percent' => $percent,
            'progress' => $percent,
            'go_live_ready' => $total > 0 && $allRequiredDone,
            'next_actions' => $nextActions,
            'steps' => $steps,
        ];
    }
}
