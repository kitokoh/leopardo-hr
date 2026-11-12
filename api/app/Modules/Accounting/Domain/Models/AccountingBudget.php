<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Enveloppe budgétaire (issue #9375).
 *
 * Un budget porte sur un axe (ou tous), un exercice, et éventuellement un
 * préfixe de compte — ce qui permet un budget global (`account_prefix = null`)
 * ou détaillé par nature de charge (`'6'` = toutes charges, `'60'` = achats).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $cost_center_id
 * @property int $year
 * @property string|null $account_prefix
 * @property float $amount
 * @property string|null $label
 */
class AccountingBudget extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_budgets';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'cost_center_id',
        'year',
        'account_prefix',
        'amount',
        'label',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'amount' => 'float',
        ];
    }
}
