<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
use App\Modules\Recruitment\Domain\Models\JobPosting;

/**
 * Cas d'usage : clôture d'une offre. Seule une offre publiée peut être close.
 */
class CloseJobPostingAction
{
    public function execute(JobPosting $jobPosting): JobPosting
    {
        if ($jobPosting->status !== 'published') {
            throw new JobPostingStateTransitionException('JOB_POSTING_PUBLISHED_ONLY_CLOSE');
        }

        $jobPosting->update(['status' => 'closed']);

        return $jobPosting->fresh() ?? $jobPosting;
    }
}
