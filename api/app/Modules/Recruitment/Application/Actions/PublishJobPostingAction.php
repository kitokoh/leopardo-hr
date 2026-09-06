<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : publication d'une offre. Seul un brouillon peut être publié.
 */
class PublishJobPostingAction
{
    public function execute(JobPosting $jobPosting): JobPosting
    {
        if ($jobPosting->status !== 'draft') {
            throw new JobPostingStateTransitionException('JOB_POSTING_DRAFT_ONLY_PUBLISH');
        }

        $jobPosting->update([
            'status' => 'published',
            'published_at' => Carbon::now(),
        ]);

        return $jobPosting->fresh() ?? $jobPosting;
    }
}
