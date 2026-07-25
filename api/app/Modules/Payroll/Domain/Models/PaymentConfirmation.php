<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Models;

use App\Shared\Traits\Auditable;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $company_id
 * @property int $payment_batch_id
 * @property int $payment_item_id
 * @property int $employee_id
 * @property string $status
 * @property Carbon $confirmed_at
 * @property string|null $device_signature
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $document_version
 * @property string|null $document_hash
 * @property array<string, mixed>|null $metadata
 *
 * @mixin Builder<static>
 */
class PaymentConfirmation extends Model
{
    // PA2-PAY-006 - Every consent/signature confirmation is written to
    // audit_logs (who confirmed, when, and the resulting document hash),
    // giving the "consent model" a durable, tamper-evident audit trail
    // without introducing a premature PKI/certificate stack.
    use Auditable;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'payment_batch_id',
        'payment_item_id',
        'employee_id',
        'status',
        'confirmed_at',
        'device_signature',
        'ip_address',
        'user_agent',
        'document_version',
        'document_hash',
        'metadata',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<PaymentItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PaymentItem::class, 'payment_item_id');
    }
}
