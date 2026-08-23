<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Période comptable clôturée — une fois fermée, plus aucun posting n'est
 * accepté pour cette période (le journal est figé, audit trail RGPD).
 * Issue #5234.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $period
 * @property string|null $closed_by
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingClosedPeriod extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_closed_periods';

    protected $fillable = [
        'company_id',
        'period',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];
}
