<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
// Note: App\Modules\Payroll\Domain\Models\Commission is intentionally NOT imported here.
// Partner (Billing) must not depend on Payroll's Domain layer — that would create a
// circular Domain<->Domain dependency (Partner -> Commission -> Partner).
// The `commissions()` relation uses the FQCN string so that Eloquent can resolve the
// model at runtime without introducing a compile-time cross-module dependency.
// See: docs/architecture/adr/0005-billing-payroll-domain-boundary.md  — Issue #1395.
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $referral_code
 * @property int $default_commission_rate
 * @property string $status
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'referral_code',
        'default_commission_rate',
        'tax_rate',
        'status',
        'type',
        'application_status',
        'payment_details',
        'payout_threshold',
        'payout_cycle',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Company, $this> */
    public function referredCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'referrer_partner_id');
    }

    /** @return HasMany<\App\Modules\Payroll\Domain\Models\Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(\App\Modules\Payroll\Domain\Models\Commission::class);
    }
}
