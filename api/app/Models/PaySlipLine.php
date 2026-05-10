<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaySlipLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pay_slip_id', 'salary_component_id', 'name', 'type',
        'base_amount', 'rate', 'amount', 'order',
    ];

    protected $casts = [
        'base_amount' => 'float',
        'rate' => 'float',
        'amount' => 'float',
        'order' => 'integer',
    ];

    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class, 'pay_slip_id');
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
