<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Sequence de numerotation LEGALE des factures Retail (BC-17, #7813).
 *
 * Un compteur par (tenant, annee civile) : `next_number` est le PROCHAIN
 * numero a attribuer. Ecritures UNIQUEMENT via RetailInvoiceService
 * (transaction + `lockForUpdate`) — jamais en direct, sous peine de trous
 * ou de doublons dans la numerotation. Unique `(company_id, year)` en base.
 *
 * @property int $id
 * @property string $company_id
 * @property int $year
 * @property int $next_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class RetailInvoiceSequence extends Model
{
    use BelongsToCompany;

    protected $table = 'retail_invoice_sequences';

    protected $fillable = [
        'company_id',
        'year',
        'next_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
