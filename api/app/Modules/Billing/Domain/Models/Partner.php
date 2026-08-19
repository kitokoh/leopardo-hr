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
use App\Modules\Payroll\Domain\Models\Commission;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int $tax_rate
 * @property int $payout_threshold
 * @property string $status
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
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
        // Issue #4186 : coordonnées de candidature (Growth) — colonnes additives
        // 2026_08_16_000001, nullable pour compatibilité avec les lignes existantes.
        'name',
        'email',
        'phone',
        'website',
        'company_id',
        'employee_id',
        'commission_rate',
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

    /** @return HasMany<Commission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * Issue #4383 : la colonne decimal(6,4) revient « 0.1500 » — cast float pour
     * un contrat API propre (0.15) et un assert exact côté tests.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'commission_rate' => 'float',
        ];
    }
}
