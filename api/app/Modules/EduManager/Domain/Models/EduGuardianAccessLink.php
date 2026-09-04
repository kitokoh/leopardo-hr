<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lien d'accès au portail responsable légal — Issue #5829 (EDU-013).
 *
 * Un lien = un accès à usage unique et expirable au portail guardian.
 * Seul `token_hash` est persisté (SHA-256) : le token brut n'est renvoyé
 * qu'une seule fois à l'émission et n'est jamais stocké en clair (PII /
 * secret — hors logs, hors réponses d'erreur, hors fixtures).
 *
 * @property int $id
 * @property string $company_id
 * @property int $guardian_id
 * @property string $token_hash
 * @property string $purpose
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduGuardianAccessLink extends Model
{
    use BelongsToCompany;

    public const PURPOSE_PORTAL_ACCESS = 'portal_access';

    public const PURPOSES = [
        self::PURPOSE_PORTAL_ACCESS,
    ];

    protected $table = 'edu_guardian_access_links';

    protected $fillable = [
        'company_id',
        'guardian_id',
        'token_hash',
        'purpose',
        'expires_at',
        'used_at',
        'created_by',
    ];

    protected $casts = [
        'guardian_id' => 'integer',
        'purpose' => 'string',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_by' => 'integer',
    ];

    /**
     * @param  Builder<static>  $query
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->where('expires_at', '>', now())
            ->whereNull('used_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
