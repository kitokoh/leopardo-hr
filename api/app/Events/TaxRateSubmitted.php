<?php

declare(strict_types=1);

namespace App\Events;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Issue #1813 — une modification de taux légal (barème fiscal ou cotisation
 * sociale) a été soumise pour validation par un comptable/principal.
 */
class TaxRateSubmitted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Model  $model  TaxSlab|SocialContribution
     */
    public function __construct(
        public readonly Model $model,
        public readonly Employee $actor,
    ) {}
}
