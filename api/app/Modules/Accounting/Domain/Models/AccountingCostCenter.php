<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Axe analytique du tenant (issue #9375).
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 * @property bool $is_active
 */
class AccountingCostCenter extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_cost_centers';

    /** @var list<string> */
    protected $fillable = ['company_id', 'code', 'label', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
