<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne de relevé bancaire (Phase D — rapprochement bancaire #5435).
 *
 * Une ligne est `pending` tant qu'aucun paiement n'est rapproché, puis
 * `matched` avec le paiement (`matched_payment_id`) et le score de confiance
 * du matching (100 = exact, sinon approximatif).
 *
 * @property string $id
 * @property string $statement_id
 * @property string|null $company_id
 * @property int $line_number
 * @property Carbon $line_date
 * @property string $label
 * @property float $amount
 * @property string|null $external_reference
 * @property string|null $category
 * @property string $status
 * @property string|null $matched_payment_id
 * @property int|null $confidence
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankStatement $statement
 * @property-read AccountingPayment|null $matchedPayment
 *
 * @mixin Builder<static>
 */
class BankStatementLine extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'bank_statement_lines';

    protected $fillable = [
        'company_id',
        'statement_id',
        'line_number',
        'line_date',
        'label',
        'amount',
        'external_reference',
        'category',
        'status',
        'matched_payment_id',
        'confidence',
        'metadata',
    ];

    protected $casts = [
        'line_date' => 'date',
        'amount' => 'float',
        'confidence' => 'integer',
        'metadata' => 'encrypted:array',
    ];

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    /**
     * @return BelongsTo<AccountingPayment, $this>
     */
    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(AccountingPayment::class, 'matched_payment_id');
    }
}
