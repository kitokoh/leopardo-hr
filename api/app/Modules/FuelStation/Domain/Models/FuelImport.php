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
 * Import CSV FuelStation — FUEL-018 (#5812).
 *
 * Journal d'import avec validation ligne à ligne, preview (dry-run),
 * rollback logique (zéro écriture si une ligne est invalide), limites
 * taille/lignes et audit (imported_by, erreurs par ligne).
 *
 * @property int $id
 * @property string $company_id
 * @property string $import_type products|equipment|shifts|readings
 * @property string $filename
 * @property string $status pending|validated|completed|failed
 * @property int $total_lines
 * @property int $valid_lines
 * @property int $error_lines
 * @property array<int, array<string, mixed>>|null $errors
 * @property int|null $imported_by
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
    public const TYPE_PRODUCTS = 'products';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_SHIFTS = 'shifts';

    public const TYPE_READINGS = 'readings';

    public const TYPES = [
        self::TYPE_PRODUCTS,
        self::TYPE_EQUIPMENT,
        self::TYPE_SHIFTS,
        self::TYPE_READINGS,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VALIDATED,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /** Limites d'import (spec FUEL-018). */
    public const MAX_LINES = 5000;

    public const MAX_FILE_BYTES = 2 * 1024 * 1024;

    protected $fillable = [
        'company_id',
        'import_type',
        'filename',
        'status',
        'total_lines',
        'valid_lines',
        'error_lines',
        'errors',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'total_lines' => 'integer',
            'valid_lines' => 'integer',
            'error_lines' => 'integer',
            'errors' => 'array',
            'imported_by' => 'integer',
        ];
    }
}
