<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Modules\Onboarding\Application\DTOs\CompleteStepDTO;
use App\Models\OnboardingStep;
use Illuminate\Support\Carbon;

/**
 * Use Case: Mark an onboarding step as completed.
 */
final class CompleteStep
{
    public function execute(OnboardingStep $step, CompleteStepDTO $dto): OnboardingStep
    {
        $step->update([
            'status'       => 'completed',
            'completed_at' => Carbon::now(),
            'note'         => $dto->note,
        ]);

        return $step->fresh() ?? $step;
    }
}
