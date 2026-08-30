<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Entrée du journal de fidélité (TRAVEL-811, issue #6101).
 *
 * Idempotence : un billet ne crédite qu'UNE fois (unique company+ticket),
 * une récompense ne débite qu'UNE fois par réservation (unique
 * company+booking+type).
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
