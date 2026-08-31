<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Jeton de la boutique publique (TRAVEL-1001, issue #6114).
 *
 * Un par tenant ; seul le hash SHA-256 est persisté. La rotation
 * (régénération) invalide l'ancien jeton.
 */
class TravelPublicShopToken extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'token_hash',
        'name',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
