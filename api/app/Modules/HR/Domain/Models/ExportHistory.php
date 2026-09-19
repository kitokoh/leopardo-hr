<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
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
    // Issue #7711 (suite #7646) — table `export_history` du schéma partagé
    // shared_tenants (journal append-only #2199) : company_id est l'unique
    // frontière d'isolation.
    use BelongsToCompany;

    public $timestamps = false;

    protected $table = 'export_history';

    protected $fillable = [
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
