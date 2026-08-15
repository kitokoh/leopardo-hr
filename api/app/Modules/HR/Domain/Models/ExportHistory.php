<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Historique des exports du portail manager (append-only) — issue #2199.
 *
 * @property int $id
 * @property string $company_id
 * @property string|null $employee_id
 * @property string $type
 * @property string|null $format
 * @property int|null $record_count
 * @property string|null $filename
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ExportHistory extends Model
{
    public $timestamps = false;

    protected $table = 'export_history';

    protected $fillable = [
        'company_id',
        'employee_id',
        'type',
        'format',
        'record_count',
        'filename',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'record_count' => 'integer',
        'created_at' => 'datetime',
    ];
}
