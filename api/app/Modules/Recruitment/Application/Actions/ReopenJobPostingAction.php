<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Exceptions\JobPostingStateTransitionException;
use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : republication d'une offre clôturée ou archivée.
 *
 * La date de clôture est remise à NULL : conserver l'ancienne `closes_at`
 * ferait réapparaître l'offre sur le portail pour la faire disparaître
 * aussitôt (le portail filtre sur `closes_at >= now()`), ce qui se lirait
 * comme un bug. `published_at` est rafraîchi pour que le tri « plus récentes »
 * reflète la republication.
 */
class ReopenJobPostingAction
{
    public function execute(JobPosting $jobPosting): JobPosting
    {
        if (! in_array($jobPosting->status, ['closed', 'archived'], true)) {
            throw new JobPostingStateTransitionException('JOB_POSTING_CLOSED_OR_ARCHIVED_ONLY_REOPEN');
        }

        $jobPosting->update([
            'status' => 'published',
            'published_at' => Carbon::now(),
            'closes_at' => null,
            'archived_at' => null,
        ]);

        return $jobPosting->fresh() ?? $jobPosting;
    }
}
