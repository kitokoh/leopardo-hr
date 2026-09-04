<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton d'API entrante d'un transporteur (TRAVEL-807, issue #6086).
 *
 * Seul le hash SHA-256 du jeton est persisté (`token_hash`) — le jeton en
 * clair n'est jamais stocké ni exposé.
 */
class TravelCarrierToken extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'carrier_id',
        'name',
        'token_hash',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TravelCarrier, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(TravelCarrier::class, 'carrier_id');
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
