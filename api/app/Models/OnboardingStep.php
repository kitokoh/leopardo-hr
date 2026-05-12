<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $step_key
 * @property string $title
 * @property string $description
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $completed_by
 * @property int $order
 * @property bool $required
 * @property array<mixed> $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OnboardingStep extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'step_key',
        'title',
        'description',
        'status',
        'completed_at',
        'completed_by',
        'order',
        'required',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'required' => 'boolean',
            'order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
