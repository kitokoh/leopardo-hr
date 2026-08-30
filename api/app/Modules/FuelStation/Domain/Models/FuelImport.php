<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
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
