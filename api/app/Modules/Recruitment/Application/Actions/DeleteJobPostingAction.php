<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
use App\Modules\Recruitment\Domain\Models\JobPosting;

/**
 * Cas d'usage : suppression d'une offre. Seul un brouillon peut être supprimé.
 */
class DeleteJobPostingAction
{
    public function execute(JobPosting $jobPosting): void
    {
        if ($jobPosting->status !== 'draft') {
            throw new JobPostingStateTransitionException('JOB_POSTING_DRAFT_ONLY_DELETE');
        }

        $jobPosting->delete();
    }
}
