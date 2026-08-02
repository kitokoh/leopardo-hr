<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for reading recruitment pipeline aggregates needed by HR's
 * cross-domain analytics (AdvancedReportController).
 *
 * Owned by HR (the consumer) so HR depends on this interface instead of
 * importing App\Modules\Recruitment\Domain\Models\Applicant directly
 * (PA2-ARCH-003). The concrete implementation lives in the Recruitment
 * module and is bound in HRServiceProvider.
 */
interface ApplicantPipelineReaderInterface
{
    /**
     * Number of applicants per pipeline status for a given company.
     *
     * @return Collection<string, int> keyed by status, value is the count
     */
    public function countByStatus(string $companyId): Collection;
}
