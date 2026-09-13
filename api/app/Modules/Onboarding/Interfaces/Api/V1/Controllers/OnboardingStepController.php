<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OnboardingStepResource;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use App\Modules\Onboarding\Application\Actions\SyncOnboardingCompletion;
use App\Modules\Onboarding\Application\Services\OnboardingProgressReader;
use App\Modules\Onboarding\Application\Services\StepCompletionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnboardingStepController extends Controller
{
    public function __construct(
        private readonly StepCompletionGuard $stepCompletionGuard,
        private readonly OnboardingProgressReader $progressReader,
    ) {}

    /**
     * Checklist pilotée par la table `onboarding_steps` — SOURCE DE VÉRITÉ
     * de la progression d'onboarding (#7300).
     *
     * #3239 — shape alignée sur le moteur calculé : data{ completed_steps,
     * total_steps, progress_percent, progress (alias), go_live_ready,
     * next_actions, steps }. La collection d'étapes reste exposée telle quelle
     * sous `data.steps`.
     *
     * #7300 — les nombres proviennent désormais de `OnboardingProgressReader`.
     * Ils étaient auparavant recalculés ici, dans `progress()`, dans le moteur
     * calculé et dans le back-office : quatre implémentations d'une même règle,
     * qui avaient divergé.
     */
    public function checklist(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        if ($companyId === null) {
            return $this->errorResponse('COMPANY_CONTEXT_REQUIRED', 403);
        }

        $progress = $this->progressReader->read($companyId);

        if ($progress['total_steps'] === 0) {
            // #4929 : seed paresseux via l'action canonique (source de vérité
            // unique des 10 étapes) — couvre les sociétés créées avant le
            // correctif provisioning.
            app(SeedDefaultSteps::class)->execute($companyId);
            $progress = $this->progressReader->read($companyId);
        }

        // #R6 — exposer employees_count depuis ce endpoint (évite au wizard
        // d'appeler le moteur calculé séparément pour le Quick Start).
        $employeesCount = Employee::where('company_id', $companyId)->count();

        // #5151 — instrumentation légère (sans outil externe) : horodatage du
        // parcours pilote exposé au gestionnaire. Champs additifs — les
        // clients existants ignorent les clés inconnues (contrat canonique
        // inchangé : completed_steps/total_steps/progress_percent/steps…).
        $company = Company::find($companyId);
        $companyCreatedAt = $company?->created_at;

        return response()->json([
            'data' => [
                'completed_steps' => $progress['completed_steps'],
                'total_steps' => $progress['total_steps'],
                'progress_percent' => $progress['progress_percent'],
                'progress' => $progress['progress'],
                'go_live_ready' => $progress['go_live_ready'],
                'employees_count' => $employeesCount,
                'company_created_at' => $companyCreatedAt?->toIso8601String(),
                'elapsed_since_company_creation_minutes' => $companyCreatedAt
                    ? (int) $companyCreatedAt->diffInMinutes(now())
                    : null,
                'next_actions' => $progress['next_actions'],
                'steps' => $progress['steps']
                    ->map(fn (OnboardingStep $step): array => (new OnboardingStepResource($step))->resolve($request))
                    ->all(),
            ],
        ]);
    }

    public function progress(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        if ($companyId === null) {
            return $this->errorResponse('COMPANY_CONTEXT_REQUIRED', 403);
        }

        // #7300 — même lecteur canonique que `checklist()` : les deux endpoints
        // ne peuvent plus annoncer deux progressions différentes.
        $progress = $this->progressReader->read($companyId);

        return response()->json([
            'data' => [
                'progress' => $progress['progress'],
                'progress_percent' => $progress['progress_percent'],
                'completed' => $progress['completed_steps'],
                'total' => $progress['total_steps'],
            ],
        ]);
    }

    /**
     * #7268 — reponse d'erreur localisee du module Onboarding.
     *
     * `error` et `message` portent le code stable (machine), `localized_message`
     * porte la traduction `errors.*` dans la langue resolue par SetLocale. Le
     * portail lit `localized_message` en priorite (cf. `getApiErrorMessage`),
     * donc aucune chaine affichable n'est codee en dur cote API.
     */
    private function errorResponse(string $code, int $status): JsonResponse
    {
        $translated = __("errors.{$code}");

        return new JsonResponse([
            'error' => $code,
            'message' => $code,
            'localized_message' => is_string($translated) ? $translated : $code,
        ], $status);
    }

    public function complete(Request $request, string $stepKey): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        if ($companyId === null) {
            return $this->errorResponse('COMPANY_CONTEXT_REQUIRED', 403);
        }

        // #4929 : le PATCH ne doit pas dépendre de l'ordre des appels client —
        // si la société n'a aucune étape seedée (provisioning antérieur au
        // correctif), on seede avant de résoudre. Une clé inconnue reste 404.
        $hasSteps = OnboardingStep::where('company_id', $companyId)->exists();
        if (! $hasSteps) {
            app(SeedDefaultSteps::class)->execute($companyId);
        }

        $step = OnboardingStep::where('company_id', $companyId)
            ->where('step_key', $stepKey)
            ->firstOrFail();

        // #7261 — une étape ne peut être marquée terminée que si l'action
        // correspondante est constatée côté serveur. `null` signifie « étape
        // sans prédicat » (déclarative) : on ne bloque pas le client.
        if ($this->stepCompletionGuard->isSatisfied($companyId, $stepKey) === false) {
            return $this->errorResponse('ONBOARDING_STEP_NOT_DONE', 422);
        }

        $step->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        // #7262 — l'etat « onboarding termine » n'existait que dans le
        // localStorage du navigateur : le serveur en est desormais la source de
        // verite (voir SyncOnboardingCompletion).
        app(SyncOnboardingCompletion::class)->execute($companyId);

        // #5151 — instrumentation légère : horodatage par étape du parcours
        // pilote (log structuré, pas d'outil externe). `elapsed_minutes` =
        // temps écoulé depuis la création de la société → permet de mesurer
        // l'objectif « onboarding pilote < 30 min » sans télémétrie tierce.
        $companyCreatedAt = Company::find($companyId)?->created_at;
        Log::info('onboarding.step_completed', [
            'company_id' => $companyId,
            'step_key' => $stepKey,
            'step_order' => $step->order,
            'step_title' => $step->title,
            'completed_at' => now()->toIso8601String(),
            'elapsed_minutes_since_company_creation' => $companyCreatedAt
                ? (int) $companyCreatedAt->diffInMinutes(now())
                : null,
        ]);

        return (new OnboardingStepResource($step->fresh()))->response();
    }

    public function skip(Request $request, string $stepKey): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        if ($companyId === null) {
            return $this->errorResponse('COMPANY_CONTEXT_REQUIRED', 403);
        }

        // #4929 : le PATCH ne doit pas dépendre de l'ordre des appels client —
        // si la société n'a aucune étape seedée (provisioning antérieur au
        // correctif), on seede avant de résoudre. Une clé inconnue reste 404.
        $hasSteps = OnboardingStep::where('company_id', $companyId)->exists();
        if (! $hasSteps) {
            app(SeedDefaultSteps::class)->execute($companyId);
        }

        $step = OnboardingStep::where('company_id', $companyId)
            ->where('step_key', $stepKey)
            ->firstOrFail();

        if ($step->required) {
            // #7268 — le refus etait un litteral anglais en dur, affiche tel
            // quel par le portail alors que le produit est multilingue
            // (fr/en/ar/tr). On suit la convention d'erreur du module (cf.
            // OnboardingController::errorResponse) : code stable + code
            // traduit par le catalogue `errors.*` dans la langue resolue par
            // le middleware SetLocale. Le portail affiche `localized_message`.
            return $this->errorResponse('ONBOARDING_STEP_REQUIRED', 422);
        }

        $step->update(['status' => 'skipped']);
        app(SyncOnboardingCompletion::class)->execute($companyId);

        return (new OnboardingStepResource($step->fresh()))->response();
    }
}
