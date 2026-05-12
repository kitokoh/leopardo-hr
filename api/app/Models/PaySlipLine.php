<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $pay_slip_id
 * @property int|null $salary_component_id
 * @property string $name
 * @property string $type
 * @property float $base_amount
 * @property float $rate
 * @property float $amount
 * @property int $order
 */
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

    /** @return BelongsTo<PaySlip, $this> */
    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(PaySlip::class, 'pay_slip_id');
    }

    /** @return BelongsTo<SalaryComponent, $this> */
    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
