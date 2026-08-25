<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Relevé bancaire importé (Phase D — rapprochement bancaire #5435).
 *
 * Un relevé est identifié de façon unique par (company_id, statement_period,
 * import_reference) : le ré-import du même relevé est refusé (409).
 *
 * @property string $id
 * @property string|null $company_id
 * @property string $statement_period
 * @property string $import_reference
 * @property float|null $opening_balance
 * @property float|null $closing_balance
 * @property string $status
 * @property string|null $file_hash
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BankStatementLine> $lines
 *
 * @mixin Builder<static>
 */
class BankStatement extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'bank_statements';

    protected $fillable = [
        'company_id',
        'statement_period',
        'import_reference',
        'opening_balance',
        'closing_balance',
        'status',
        'file_hash',
        'metadata',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'closing_balance' => 'float',
        'metadata' => 'encrypted:array',
    ];

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'statement_id');
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function pendingLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'statement_id')->where('status', 'pending');
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function matchedLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'statement_id')->where('status', 'matched');
    }
}
