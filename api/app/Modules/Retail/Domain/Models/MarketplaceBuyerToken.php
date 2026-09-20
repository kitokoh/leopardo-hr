<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Jeton d'authentification d'un compte acheteur marketplace
 * (BC-17 RETAIL, #7814).
 *
 * Le jeton en clair (`mkb_` + 64 hex) n'est JAMAIS persiste : seul son
 * hash SHA-256 est stocke (`token_hash`, unique). Expiration serveur via
 * `expires_at`, revocation = suppression de la ligne (logout).
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $token_hash
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class MarketplaceBuyerToken extends Model
{
    protected $table = 'marketplace_buyer_tokens';

    /** @var list<string> */
    protected $fillable = [
        'buyer_id',
        'token_hash',
        'expires_at',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buyer_id' => 'integer',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
