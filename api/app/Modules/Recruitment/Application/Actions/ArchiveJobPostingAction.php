<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : archivage d'une offre. Seule une offre CLÔTURÉE peut être
 * archivée — archiver une offre encore publiée la retirerait du portail sans
 * clôture explicite, ce qui rendrait la disparition inexplicable côté candidat.
 */
class ArchiveJobPostingAction
{
    public function execute(JobPosting $jobPosting): JobPosting
    {
        if ($jobPosting->status !== 'closed') {
            throw new JobPostingStateTransitionException('JOB_POSTING_CLOSED_ONLY_ARCHIVE');
        }

        $jobPosting->update([
            'status' => 'archived',
            'archived_at' => Carbon::now(),
        ]);

        return $jobPosting->fresh() ?? $jobPosting;
    }
}
