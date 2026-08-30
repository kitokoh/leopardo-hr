<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Opt-out destinataire (DELIVERY-206, issue #6290) — arrête les notifications
 * planifiées (jamais les déjà envoyées).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $phone
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class DeliveryRecipientOptOut extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_recipient_opt_outs';

    protected $fillable = [
        'company_id',
        'phone',
    ];
}
