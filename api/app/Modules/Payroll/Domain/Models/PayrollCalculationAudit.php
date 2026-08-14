<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Issue #1874 — audit d'un calcul de paie (simulation ou run).
 *
 * Une ligne = un calcul : tenant (company_id), acteur (job ou utilisateur),
 * pays, version/période des règles, entrées NON sensibles (brut agrégé),
 * résultats agrégés (net, coût employeur, impôt), identifiant de
 * corrélation (uuid) et statut. Par construction, aucune donnée sensible
 * (token, mot de passe, biométrie brute) ne peut y être enregistrée — seuls
 * des champs whitelistés.
 *
 * @property string $id
 * @property int $company_id
 * @property int|null $actor_id
 * @property string|null $actor_role
 * @property string $country_code
 * @property string|null $rules_version
 * @property string|null $rules_period
 * @property string $correlation_id
 * @property float $input_gross
 * @property float|null $result_net
 * @property float|null $result_total_cost
 * @property float|null $result_income_tax
 * @property string $status 'ok'|'error'
 * @property string|null $error_class
 * @property Carbon $created_at
 *
 * @mixin Builder<static>
 */
class PayrollCalculationAudit extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    protected $table = 'payroll_calculation_audits';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'input_gross' => 'float',
            'result_net' => 'float',
            'result_total_cost' => 'float',
            'result_income_tax' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
