<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'import FuelStation — FUEL-018 (issue #5812).
 *
 * Suivi des imports CSV (relevés, stock, produits) : statut, compteurs,
 * résumé d'erreurs, fichier d'origine. Asynchrone et rejouable.
 *
 * @property int $id
 * @property string $company_id
 * @property string $kind meter_readings|stock_entries|products
 * @property string $file_name
 * @property string $status uploaded|processing|completed|failed
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $failed_rows
 * @property array<string, mixed>|null $error_summary
 * @property int|null $created_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Session d'import CSV FuelStation (FUEL-018, issue #5812).
 *
 * Cycle : previewed → committing → committed | failed | cancelled. Le
 * preview ne touche jamais les tables cibles ; le commit est idempotent
 * (claim atomique de statut) ; le rollback logique est possible avant
 * commit. Lignes validées ligne par ligne, limites taille/lignes, audit.
 *
 * @property int $id
 * @property string $company_id
 * @property string $entity_type products|pumps|tanks|shifts|readings
 * @property string $filename
 * @property string $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $error_rows
 * @property array<string, mixed>|null $columns
 * @property array<mixed>|null $preview_data
 * @property array<mixed>|null $errors
 * @property array<mixed>|null $raw_rows
 * @property array<string, mixed>|null $result
 * @property int|null $created_by
 * @property int|null $committed_by
 * @property int|null $cancelled_by
 * @property Carbon|null $committed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class FuelImport extends Model
{
    use BelongsToCompany;

    protected $table = 'fuel_imports';

    public const KIND_METER_READINGS = 'meter_readings';

    public const KIND_STOCK_ENTRIES = 'stock_entries';

    public const KIND_PRODUCTS = 'products';

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'kind',
        'file_name',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'error_summary',
        'created_by',
        'started_at',
        'finished_at',
    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_COMMITTING = 'committing';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PREVIEWED,
        self::STATUS_COMMITTING,
        self::STATUS_COMMITTED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    public const ENTITY_PRODUCTS = 'products';

    public const ENTITY_PUMPS = 'pumps';

    public const ENTITY_TANKS = 'tanks';

    public const ENTITY_SHIFTS = 'shifts';

    public const ENTITY_READINGS = 'readings';

    public const ENTITIES = [
        self::ENTITY_PRODUCTS,
        self::ENTITY_PUMPS,
        self::ENTITY_TANKS,
        self::ENTITY_SHIFTS,
        self::ENTITY_READINGS,
    ];

    protected $fillable = [
        'company_id',
        'entity_type',
        'filename',
        'status',
        'total_rows',
        'valid_rows',
        'error_rows',
        'columns',
        'preview_data',
        'errors',
        'raw_rows',
        'result',
        'created_by',
        'committed_by',
        'cancelled_by',
        'committed_at',
        'cancelled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'error_summary' => 'array',
            'created_by' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
            'valid_rows' => 'integer',
            'error_rows' => 'integer',
            'columns' => 'array',
            'preview_data' => 'array',
            'errors' => 'array',
            'raw_rows' => 'array',
            'result' => 'array',
            'committed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
