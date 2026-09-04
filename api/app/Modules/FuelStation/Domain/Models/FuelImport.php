<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    public const ENTITIES = [
            self::ENTITY_PRODUCTS,
            self::ENTITY_PUMPS,
            self::ENTITY_TANKS,
            self::ENTITY_SHIFTS,
            self::ENTITY_READINGS,
        ];
    public const ENTITY_PRODUCTS = 'products';
    public const ENTITY_PUMPS = 'pumps';
    public const ENTITY_READINGS = 'readings';
    public const ENTITY_SHIFTS = 'shifts';
    public const ENTITY_TANKS = 'tanks';
    public const MAX_FILE_BYTES = 2 * 1024 * 1024;
    public const MAX_LINES = 5000;
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_COMMITTING = 'committing';
    public const STATUS_PREVIEWED = 'previewed';
    public const STATUS_VALIDATED = 'validated';
    public const TYPES = [
            self::TYPE_PRODUCTS,
            self::TYPE_EQUIPMENT,
            self::TYPE_SHIFTS,
            self::TYPE_READINGS,
        ];
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_PRODUCTS = 'products';
    public const TYPE_READINGS = 'readings';
    public const TYPE_SHIFTS = 'shifts';

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
            'raw_rows' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'error_summary' => 'array',
            'created_by' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
