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
 * @property string $entity_type products|pumps|tanks|shifts|readings
 * @property string $filename
 * @property string $status previewed|committing|committed|cancelled|failed
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $error_rows
 * @property array<int, string>|null $columns
 * @property array<int, array<string, mixed>>|null $preview_data
 * @property array<int, array<string, mixed>>|null $errors
 * @property array<int, array<string, mixed>>|null $raw_rows
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

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_COMMITTING = 'committing';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const MAX_FILE_BYTES = 2 * 1024 * 1024;

    public const MAX_LINES = 5000;

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
            'result' => 'array',
            'committed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'total_rows' => 'integer',
            'valid_rows' => 'integer',
            'error_rows' => 'integer',
            'columns' => 'array',
            'preview_data' => 'array',
            'errors' => 'array',
            'created_by' => 'integer',
            'committed_by' => 'integer',
            'cancelled_by' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
