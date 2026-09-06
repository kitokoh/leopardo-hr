<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Application\Actions;

use App\Modules\Recruitment\Domain\Models\JobPosting;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : mise à jour d'une offre. La transition draft → published
 * horodate la publication (published_at) au premier passage.
 */
class UpdateJobPostingAction
{
    /**
     * @param  array<string, mixed>  $data  champs validés
     */
    public function execute(JobPosting $jobPosting, array $data): JobPosting
    {
        if (isset($data['status']) && $data['status'] === 'published' && $jobPosting->status === 'draft') {
            $data['published_at'] = Carbon::now();
        }

        $jobPosting->update($data);

        return $jobPosting->fresh() ?? $jobPosting;
    }
}
