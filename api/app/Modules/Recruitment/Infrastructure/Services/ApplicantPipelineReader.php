<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Infrastructure\Services;

use App\Modules\HR\Domain\Contracts\ApplicantPipelineReaderInterface;
use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Support\Collection;

/**
 * Recruitment-side implementation of HR's ApplicantPipelineReaderInterface.
 * Keeps Applicant a private detail of the Recruitment module — HR only
 * ever talks to the interface (PA2-ARCH-003).
 */
class ApplicantPipelineReader implements ApplicantPipelineReaderInterface
{
    public function countByStatus(int $companyId): Collection
    {
        return Applicant::where('company_id', $companyId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
    }
}
