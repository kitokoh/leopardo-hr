<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lien d'accès expirable du portail guardian — Issue #5829 (EDU-013).
 *
 * Seul le hash sha256 du token est stocké ; `expires_at` + `used_at`
 * garantissent un usage unique et borné dans le temps (acceptation EDU-013 :
 * « liens d'accès expirables »).
 *
 * @property int $id
 * @property string $company_id
 * @property int $guardian_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class GuardianAccessToken extends Model
{
    use BelongsToCompany;

    public const DEFAULT_TTL_DAYS = 7;

    public const MAX_TTL_DAYS = 30;

    protected $table = 'edu_guardian_access_tokens';

    protected $fillable = [
        'company_id',
        'guardian_id',
        'token_hash',
        'expires_at',
        'used_at',
        'created_by',
    ];

    protected $casts = [
        'guardian_id' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isRedeemable(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }
}
