<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankExport extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'payroll_run_id', 'company_id', 'format', 'file_path',
        'total_amount', 'transfer_count', 'status',
        'generated_at', 'sent_at',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'transfer_count' => 'integer',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
