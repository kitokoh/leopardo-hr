<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property int|null $company_id
 * @property string $amount
 * @property string $currency
 * @property string $method
 * @property string|null $provider_reference
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class Payment extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'company_id',
        'amount',
        'currency',
        'method',
        'provider_reference',
        'status',
        'paid_at',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
