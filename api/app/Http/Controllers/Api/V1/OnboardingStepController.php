<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OnboardingStepResource;
use App\Models\Employee;
use App\Models\OnboardingStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingStepController extends Controller
{
    public function checklist(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $steps = OnboardingStep::where('company_id', $user->company_id)
            ->orderBy('order')
            ->get();

        if ($steps->isEmpty()) {
            $steps = $this->seedDefaultSteps($user->company_id);
        }

        return OnboardingStepResource::collection($steps)->response();
    }

    public function progress(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $steps = OnboardingStep::where('company_id', $user->company_id)->get();
        $total = $steps->count();

        if ($total === 0) {
            return response()->json(['data' => ['progress' => 0, 'completed' => 0, 'total' => 0]]);
        }

        $completed = $steps->whereIn('status', ['completed', 'skipped'])->count();

        return response()->json([
            'data' => [
                'progress' => round(($completed / $total) * 100),
                'completed' => $completed,
                'total' => $total,
            ],
        ]);
    }

    public function complete(Request $request, string $stepKey): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $step = OnboardingStep::where('company_id', $user->company_id)
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

        $step = OnboardingStep::where('company_id', $user->company_id)
            ->where('step_key', $stepKey)
            ->firstOrFail();

        if ($step->required) {
            return response()->json(['message' => 'This step is required and cannot be skipped.'], 422);
        }

        $step->update(['status' => 'skipped']);

        return (new OnboardingStepResource($step->fresh()))->response();
    }

    /**
     * @return Collection<int, OnboardingStep>
     */
    private function seedDefaultSteps(string $companyId): Collection
    {
        $defaults = [
            ['step_key' => 'company_info', 'title' => 'Renseigner les informations entreprise', 'order' => 1, 'required' => true],
            ['step_key' => 'first_department', 'title' => 'Creer le premier departement', 'order' => 2, 'required' => true],
            ['step_key' => 'first_employee', 'title' => 'Ajouter le premier employe', 'order' => 3, 'required' => true],
            ['step_key' => 'first_attendance', 'title' => 'Effectuer le premier pointage', 'order' => 4, 'required' => true],
            ['step_key' => 'invite_manager', 'title' => 'Inviter un manager', 'order' => 5, 'required' => false],
            ['step_key' => 'configure_schedules', 'title' => 'Configurer les horaires', 'order' => 6, 'required' => true],
            ['step_key' => 'first_report', 'title' => 'Generer le premier rapport mensuel', 'order' => 7, 'required' => false],
            ['step_key' => 'configure_payroll', 'title' => 'Configurer la paie', 'order' => 8, 'required' => false],
            ['step_key' => 'install_kiosk', 'title' => 'Installer un kiosk', 'order' => 9, 'required' => false],
            ['step_key' => 'activate_geofence', 'title' => 'Activer le geofence', 'order' => 10, 'required' => false],
        ];

        foreach ($defaults as $step) {
            OnboardingStep::create(array_merge($step, [
                'company_id' => $companyId,
                'status' => 'pending',
            ]));
        }

        return OnboardingStep::where('company_id', $companyId)->orderBy('order')->get();
    }
}
