<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Actions;

use App\Modules\HR\Domain\Models\OnboardingStep;
use Illuminate\Support\Carbon;

/**
 * Use Case: Skip an optional onboarding step.
 */
final class SkipStep
{
    public function execute(OnboardingStep $step): OnboardingStep
    {
        $step->update([
            'status'    => 'skipped',
            'skipped_at'=> Carbon::now(),
        ]);

        return $step->fresh() ?? $step;
    }
}

