<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Activate a trial company to a paid plan.
 */
final class ActivateCompany
{
    public function execute(Company $company, int $planId): Company
    {
        return DB::transaction(function () use ($company, $planId): Company {
            $company->update(['status' => 'active']);

            Subscription::updateOrCreate(
                ['company_id' => $company->id],
                [
                    'plan_id'    => $planId,
                    'status'     => 'active',
                    'started_at' => Carbon::now(),
                ]
            );

            return $company->fresh() ?? $company;
        });
    }
}
