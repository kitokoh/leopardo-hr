<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
