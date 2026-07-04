<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Suspend a company (stop access without deleting data).
 */
final class SuspendCompany
{
    public function execute(Company $company, string $reason = ''): Company
    {
        return DB::transaction(function () use ($company, $reason): Company {
            $company->update([
                'status' => 'suspended',
                'metadata->suspension_reason' => $reason,
            ]);

            return $company->fresh() ?? $company;
        });
    }
}
