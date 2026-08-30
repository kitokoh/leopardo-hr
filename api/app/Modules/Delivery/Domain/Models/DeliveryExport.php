<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Export async des livraisons (BC-26-D07, issue #6295).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $status
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string|null $filename
 * @property string|null $error_message
 * @property int|null $requested_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 *
 * @mixin Builder<static>
 */
class DeliveryExport extends Model
{
    use BelongsToCompany;

    protected $table = 'delivery_exports';

    protected $fillable = [
        'company_id',
        'status',
        'from_date',
        'to_date',
        'filename',
        'error_message',
        'requested_by',
        'completed_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'completed_at' => 'datetime',
    ];
}
