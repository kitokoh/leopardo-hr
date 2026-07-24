<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PA2-ADM-006 — Audit record of a super-admin impersonating a tenant
 * employee ("log in as ..."), with a mandatory reason and a hard expiry.
 *
 * `company_name`/`employee_name`/`employee_email` are a point-in-time
 * denormalized snapshot (see the migration docblock): this table is global
 * (public schema) and lists sessions across every tenant schema at once,
 * so it cannot rely on Eloquent relations into per-tenant `companies`/
 * `employees` rows without switching search_path per row.
 *
 * @property int $id
 * @property int $super_admin_id
 * @property string $company_id
 * @property int $employee_id
 * @property int|null $personal_access_token_id
 * @property string|null $company_name
 * @property string|null $employee_name
 * @property string|null $employee_email
 * @property string $reason
 * @property string|null $ip_address
 * @property Carbon $expires_at
 * @property Carbon|null $ended_at
 * @property int|null $ended_by
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class PlatformImpersonationSession extends Model
{
    public $timestamps = false;

    protected $table = 'platform_impersonation_sessions';

    protected $fillable = [
        'super_admin_id',
        'company_id',
        'employee_id',
        'personal_access_token_id',
        'company_name',
        'employee_name',
        'employee_email',
        'reason',
        'ip_address',
        'expires_at',
        'ended_at',
        'ended_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<SuperAdmin, $this> */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('ended_at')->where('expires_at', '>', now());
    }
}
