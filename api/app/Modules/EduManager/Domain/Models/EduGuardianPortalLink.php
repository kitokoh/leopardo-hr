<?php

declare(strict_types=1);

namespace App\Modules\EduManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lien d'accès expirable au portail guardian — Issue #5829 (EDU-013).
 *
 * Le `portal_token` (64 caractères aléatoires, indexé unique) EST la
 * credential — pattern AccountingDocumentShare (#5428) : les routes publiques
 * n'ont ni auth ni TenantMiddleware, le token se résout O(1). Expiration
 * (expires_at), révocation (revoked_at), dernière consultation
 * (last_accessed_at). Chaque consultation est journalisée dans
 * edu_portal_access_logs (audit RGPD).
 *
 * @property int $id
 * @property string $company_id
 * @property int $guardian_id
 * @property string $portal_token
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_accessed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class EduGuardianPortalLink extends Model
{
    use BelongsToCompany;

    protected $table = 'edu_guardian_portal_links';

    protected $fillable = [
        'company_id',
        'guardian_id',
        'portal_token',
        'expires_at',
        'revoked_at',
        'last_accessed_at',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    /** @return BelongsTo<EduGuardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(EduGuardian::class, 'guardian_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
