<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #1813 — une modification de taux légal a été approuvée par un
 * platform_admin (l'ancienne ligne active est passée en superseded).
 */
class TaxRateApproved
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Model  $model  TaxSlab|SocialContribution
     */
    public function __construct(
        public readonly Model $model,
        public readonly SuperAdmin $actor,
    ) {}
}
