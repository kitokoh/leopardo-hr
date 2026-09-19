<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $partner_id
 * @property string $company_id
 * @property int $payment_id
 * @property int $amount
 * @property string $currency
 * @property int $applied_rate
 * @property string $status
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class Commission extends Model
{
    // Issue #7711 — DÉCISION : PLATEFORME, pas de trait BelongsToCompany.
    // La table `commissions` vit dans le schéma PUBLIC (migrations
    // database/migrations/public/, module Growth), pas dans shared_tenants.
    // company_id désigne la société PARRAINÉE qui génère la commission ; les
    // lecteurs légitimes agrègent CROSS-tenant : PartnerDashboardController
    // (un partenaire voit ses commissions sur TOUTES ses sociétés filleules,
    // route pourtant sous middleware tenant — un scope global viderait le
    // dashboard) et GrowthAdminController (super-admin). L'écriture est
    // interne (CommissionService sur webhook de paiement), jamais depuis un
    // payload utilisateur.

    protected $fillable = [
        'partner_id',
        'company_id',
        'payment_id',
        'amount',
        'net_amount',
        'currency',
        'applied_rate',
        'exchange_rate',
        'original_amount',
        'original_currency',
        'status',
        'approved_at',
        'paid_at',
        'created_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
