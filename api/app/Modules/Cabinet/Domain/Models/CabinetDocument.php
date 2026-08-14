<?php

declare(strict_types=1);

namespace App\Modules\Cabinet\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int|null $folder_id
 * @property string $name
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $disk
 * @property string $path
 * @property string|null $notes
 * @property bool $read_only
 * @property string|null $document_type
 * @property int|null $pay_slip_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class CabinetDocument extends Model
{
    protected $table = 'cabinet_documents';

    protected $fillable = [
        'company_id',
        'employee_id',
        'folder_id',
        'name',
        'original_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'notes',
        'read_only',
        'document_type',
        'pay_slip_id',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'employee_id' => 'integer',
        'size' => 'integer',
        'read_only' => 'boolean',
        'pay_slip_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<CabinetFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(CabinetFolder::class, 'folder_id');
    }

    /**
     * Bulletin de paie source de l'archivage (document_type = 'payslip').
     *
     * @return BelongsTo<\App\Modules\Payroll\Domain\Models\PaySlip, $this>
     */
    public function paySlip(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Payroll\Domain\Models\PaySlip::class, 'pay_slip_id');
    }

    /**
     * @return MorphMany<CabinetShare, $this>
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(CabinetShare::class, 'shareable');
    }
}
