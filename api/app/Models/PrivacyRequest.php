<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $employee_id
 * @property string $type
 * @property string $status
 * @property array<string, mixed>|null $requested_payload
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PrivacyRequest extends Model
{
    use BelongsToCompany;

    protected $table = 'privacy_requests';

    protected $fillable = [
        'company_id',
        'employee_id',
        'type',
        'status',
        'requested_payload',
        'processed_at',
    ];

    protected $casts = [
        'requested_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
