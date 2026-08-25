<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Relance de paiement envoyée pour un document (stage J+7/J+15/J+30).
 * L'unicité (company_id, document_id, stage) garantit l'absence de doublon
 * (DoD #5229). Issue #5229.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $document_id
 * @property int $stage
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AccountingPaymentReminder extends Model
{
    use BelongsToCompany;

    protected $table = 'accounting_payment_reminders';

    protected $fillable = [
        'company_id',
        'document_id',
        'stage',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
