<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Export asynchrone d'un rapport FuelStation — FUEL-017 (#5811).
 *
 * Cycle : pending → generating → generated | failed. `file_path` pointe
 * vers un fichier serveur (jamais un chemin client) ; `expires_at` borne
 * la validité du téléchargement.
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $station_id
 * @property string $report_type
 * @property string $status pending|generating|generated|failed
 * @property string|null $file_path
 * @property Carbon|null $report_date
 * @property int|null $requested_by
 * @property Carbon|null $expires_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelReportExport extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_report_exports';

    public const STATUS_PENDING = 'pending';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_GENERATING,
        self::STATUS_GENERATED,
        self::STATUS_FAILED,
    ];

    /** Types de rapports exportables (miroir du CHECK de la migration 5811). */
    public const TYPES = [
        'daily_volumes',
        'shift_summary',
        'sales_summary',
        'stock_status',
        'variance_summary',
        'referential',
    ];

    /** Durée de validité d'un export généré (lien de téléchargement borné). */
    public const EXPORT_TTL_HOURS = 24;

    protected $fillable = [
        'company_id',
        'station_id',
        'report_type',
        'status',
        'file_path',
        'report_date',
        'requested_by',
        'expires_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'station_id' => 'integer',
            'report_date' => 'date',
            'expires_at' => 'datetime',
            'requested_by' => 'integer',
        ];
    }
}
