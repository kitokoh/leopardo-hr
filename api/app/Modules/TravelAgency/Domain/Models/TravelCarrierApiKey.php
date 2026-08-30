<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelCarrierApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clé API d'un transporteur (TRAVEL-807, issue #6086).
 *
 * Le token partenaire n'est jamais stocké en clair : seul `api_key_hash`
 * (SHA-256) est persisté — le token brut n'est affiché qu'une fois à la
 * création (pattern sync_token_hash ZKTeco).
 */
class TravelCarrierApiKey extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelCarrierApiKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'carrier_id',
        'api_key_hash',
        'label',
        'enabled',
        'last_used_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class, 'carrier_id');
    }
}
