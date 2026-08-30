<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelLoyaltyTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction de points fidélité (TRAVEL-811, issue #6101).
 *
 * `ticket_id` unique par tenant sur les earn : un billet ne crédite qu'une
 * fois (acceptance TRAVEL-811).
 */
class TravelLoyaltyTransaction extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelLoyaltyTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'points',
        'type',
        'reason',
        'ticket_id',
        'booking_id',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    /**
     * @return BelongsTo<TravelLoyaltyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TravelLoyaltyAccount::class, 'account_id');
    }
}
