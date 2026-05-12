<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $contract_id
 * @property int|null $company_id
 * @property string $amendment_type
 * @property array<mixed> $changes
 * @property \Illuminate\Support\Carbon $effective_date
 * @property string|null $reason
 * @property string|null $approved_by
 * @property string|null $document_path
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class ContractAmendment extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'contract_amendments';

    protected $fillable = [
        'contract_id',
        'company_id',
        'amendment_type',
        'changes',
        'effective_date',
        'reason',
        'approved_by',
        'document_path',
    ];

    protected $casts = [
        'changes' => 'array',
        'effective_date' => 'date',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
