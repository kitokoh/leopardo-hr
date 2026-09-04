<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }


}