<?php

declare(strict_types=1);

namespace App\Modules\Training\Application\Actions;

use App\Models\TrainingEnrollment;
use Illuminate\Support\Carbon;

/**
 * Use Case: Mark a training enrollment as completed.
 */
final class CompleteEnrollment
{
    public function execute(TrainingEnrollment $enrollment, ?int $score = null): TrainingEnrollment
    {
        $enrollment->update([
            'status'       => 'completed',
            'completed_at' => Carbon::now(),
            'score'        => $score,
        ]);

        return $enrollment->fresh() ?? $enrollment;
    }
}
