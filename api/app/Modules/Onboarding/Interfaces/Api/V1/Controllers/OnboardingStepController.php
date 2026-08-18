<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OnboardingStepResource;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\HR\Domain\Models\OnboardingStep;
use App\Modules\Onboarding\Application\Actions\SeedDefaultSteps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingStepController extends Controller
{
    /**
     * Checklist pilotée par la table `onboarding_steps`.
     *
     * #3239 — shape alignée sur le moteur calculé canonique
     * (OnboardingChecklistController) : data{ completed_steps, total_steps,
     * progress_percent, progress (alias), go_live_ready, next_actions, steps }.
     * La collection d'étapes reste exposée telle quelle sous `data.steps`.
     */
    public function checklist(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        abort_if($companyId === null, 403, 'A company context is required.');

        $steps = OnboardingStep::where('company_id', $companyId)
            ->orderBy('order')
            ->get();

        if ($steps->isEmpty()) {
            // #4929 : seed paresseux via l'action canonique (source de vérité
            // unique des 10 étapes) — couvre les sociétés créées avant le
            // correctif provisioning.
            app(SeedDefaultSteps::class)->execute($companyId);
            $steps = OnboardingStep::where('company_id', $companyId)
                ->orderBy('order')
                ->get();
        }

        $total = $steps->count();
        $completed = $steps->whereIn('status', ['completed', 'skipped'])->count();
        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return response()->json([
            'data' => [
                'completed_steps' => $completed,
                'total_steps' => $total,
                'progress_percent' => $percent,
                'progress' => $percent,
                'go_live_ready' => $total > 0 && $completed >= $total - 1,
                'next_actions' => $steps
                    ->where('status', 'pending')
                    ->take(3)
                    ->map(fn (OnboardingStep $step): array => [
                        'key' => $step->step_key,
                        'label' => $step->title,
                    ])
                    ->values(),
                'steps' => $steps
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
        abort_if($companyId === null, 403, 'A company context is required.');

        $steps = OnboardingStep::where('company_id', $companyId)->get();
        $total = $steps->count();

        if ($total === 0) {
            return response()->json([
                'data' => [
                    'progress' => 0,
                    'progress_percent' => 0,
                    'completed' => 0,
                    'total' => 0,
                ],
            ]);
        }

        $completed = $steps->whereIn('status', ['completed', 'skipped'])->count();
        $percent = (int) round(($completed / $total) * 100);

        return response()->json([
            'data' => [
                'progress' => $percent,
                'progress_percent' => $percent,
                'completed' => $completed,
                'total' => $total,
            ],
        ]);
    }

    public function complete(Request $request, string $stepKey): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        abort_if($companyId === null, 403, 'A company context is required.');

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

        $step->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);

        return (new OnboardingStepResource($step->fresh()))->response();
    }

    public function skip(Request $request, string $stepKey): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();
        $companyId = $user->company_id;
        abort_if($companyId === null, 403, 'A company context is required.');

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
            return response()->json(['message' => 'This step is required and cannot be skipped.'], 422);
        }

        $step->update(['status' => 'skipped']);

        return (new OnboardingStepResource($step->fresh()))->response();
    }

}
