<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Modules\Payroll\Domain\Services\PayrollLineLabels;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $pay_slip_id
 * @property int|null $salary_component_id
 * @property string|null $company_id
 * @property string $name
 * @property string $type
 * @property float $base_amount
 * @property float $rate
 * @property float $amount
 * @property int $order
 *
 * @mixin Builder<static>
 */
class PaySlipLine extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'pay_slip_id', 'salary_component_id', 'name', 'type',
        'base_amount', 'rate', 'amount', 'order',
    ];

    protected $casts = [
        'base_amount' => 'float',
        'rate' => 'float',
        'amount' => 'float',
        'order' => 'integer',
    ];

    /**
     * #9975 — la ligne hérite du tenant de SON bulletin.
     *
     * `pay_slip_lines` n'avait aucune colonne `company_id` : elle n'était
     * isolée que par jointure sur `pay_slips`. RLS ne peut pas s'appliquer sans
     * la colonne, et une fois la colonne posée en `NOT NULL`, tout writer qui
     * ne la renseigne pas casse (mesuré en CI : `GoldenAccountingInvariants`
     * échouait en `null value in column "company_id"`).
     *
     * `BelongsToCompany` remplit `company_id` depuis le tenant ACTIF. Ce n'est
     * pas suffisant ici : le moteur de paie écrit aussi hors contexte tenant
     * (console, jobs, fixtures). Dans ce cas la ligne doit hériter du tenant de
     * son bulletin — jamais d'une valeur inventée, jamais de `NULL`.
     *
     * L'ordre compte : ce listener est enregistré APRÈS celui du trait (le
     * trait est appliqué avant `booted()`), donc il ne voit `company_id` vide
     * que si le trait n'a rien posé. Il ne peut donc pas écraser un tenant
     * actif — la garde anti-spoof de #7646 reste intacte.
     */
    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            if ($line->getAttribute('company_id') !== null) {
                return;
            }

            $paySlipId = $line->getAttribute('pay_slip_id');

            if ($paySlipId === null) {
                return;
            }

            // `withoutGlobalScopes()` est volontaire : hors contexte tenant, le
            // scope `company` ne filtre rien mais lèverait sur la surface API
            // si le marqueur est posé. On lit le parent par sa clé primaire,
            // ce qui est sûr — c'est la ligne qu'on est en train de rattacher.
            $companyId = PaySlip::withoutGlobalScopes()
                ->whereKey($paySlipId)
                ->value('company_id');

            if ($companyId !== null) {
                $line->setAttribute('company_id', $companyId);
            }
        });
    }

    /** @return BelongsTo<PaySlip, $this> */
    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class, 'pay_slip_id');
    }

    /** @return BelongsTo<SalaryComponent, $this> */
    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    /**
     * Issue #5257 — libellé localisé de la ligne (i18n ×4) : les noms de
     * lignes sont persistés en FR par le moteur ; ce accessor expose le
     * libellé traduit (locale courante) via PayrollLineLabels, avec repli sur
     * le libellé brut pour les composants personnalisés.
     */
    public function getLabelAttribute(): string
    {
        return PayrollLineLabels::label((string) $this->name);
    }
}
