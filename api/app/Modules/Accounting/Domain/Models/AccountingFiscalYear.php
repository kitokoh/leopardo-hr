<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Exercice comptable d'une entreprise — issue #5422.
 * Un exercice ouvert accepte les écritures de son année ; clôturé, il est
 * figé (aucun posting) et son résultat est reporté en « report à nouveau »
 * (compte 12) par la clôture d'exercice.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $year
 * @property string $status
 * @property string|null $closed_by
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingFiscalYear extends Model
{
    use BelongsToCompany;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'accounting_fiscal_years';

    protected $fillable = [
        'company_id',
        'year',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
