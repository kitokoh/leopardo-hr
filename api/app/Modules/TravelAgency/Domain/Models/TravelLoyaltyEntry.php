<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Entrée du journal de fidélité (TRAVEL-811, issue #6101).
 *
 * Idempotence : un billet ne crédite qu'UNE fois (unique company+ticket),
 * une récompense ne débite qu'UNE fois par réservation (unique
 * company+booking+type).
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $account_id
 * @property string $booking_id
 * @property string $ticket_id
 * @property int $points
 * @property string $type
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelLoyaltyEntry extends Model
{
    use BelongsToCompany;

    public const TYPE_EARNED = 'earned';

    public const TYPE_REDEEMED = 'redeemed';

    public const TYPE_BONUS = 'bonus';

    protected $fillable = [
        'company_id',
        'account_id',
        'booking_id',
        'ticket_id',
        'points',
        'type',
        'reason',
    ];

    protected $casts = [
        'points' => 'integer',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;
}
