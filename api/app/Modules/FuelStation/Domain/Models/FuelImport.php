<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * #5812 (FUEL-018) — Trace d'audit d'un import FuelStation.
 *
 * @property int $id
 * @property string $company_id
 * @property string $type
 * @property int $rows_total
 * @property int $rows_imported
 * @property string $status
 * @property int|null $imported_by_user_id
 * @property string|null $error_summary
 */
class FuelImport extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<\Database\Factories\FuelImportFactory> */
    use HasFactory;

    protected $table = 'fuel_imports';

    protected $fillable = [
        'company_id',
        'type',
        'rows_total',
        'rows_imported',
        'status',
        'imported_by_user_id',
        'error_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rows_total' => 'integer',
            'rows_imported' => 'integer',
        ];
    }
}
