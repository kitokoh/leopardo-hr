<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application\Actions;

use App\Modules\Billing\Domain\Models\Partner;
use Illuminate\Support\Carbon;

/**
 * Use Case: Approve a partner application (super-admin or growth manager).
 */
final class ApprovePartner
{
    public function execute(Partner $partner): Partner
    {
        $partner->update([
            'status'      => 'active',
            'approved_at' => Carbon::now(),
        ]);

        return $partner->fresh() ?? $partner;
    }
}

