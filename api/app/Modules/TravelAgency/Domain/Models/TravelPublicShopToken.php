<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Jeton de la boutique publique (TRAVEL-1001, issue #6114).
 *
 * Un par tenant ; seul le hash SHA-256 est persisté. La rotation
 * (régénération) invalide l'ancien jeton.
 */
/**
 * @property int $id
 * @property string $company_id
 * @property string $token_hash
 * @property string|null $name
 * @property bool $active
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
