<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Recruitment\Domain\Models\Applicant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Issue #5261 — un candidat (Recruitment) vient d'être embauché : un
 * Employee est créé avec sa traçabilité (candidate_id).
 */
class CandidateHired
{
    use Dispatchable;

    public function __construct(
        public readonly Applicant $applicant,
        public readonly Employee $employee,
    ) {}
}
