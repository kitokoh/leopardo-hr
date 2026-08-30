<?php

declare(strict_types=1);

namespace App\AI\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BC-23-D07 (issue #6239) — dead-letter queue dédiée AI.
 *
 * Consigne les jobs IA ayant épuisé leurs retries (échec définitif) pour un
 * replay contrôlé via `php artisan ai:dlq:replay`. Unicité par `dedup_key` :
 * un même job ne peut pas être consigné deux fois.
 *
 * @property int $id
 * @property string $company_id
 * @property string $job_class
 * @property int|null $job_id
 * @property string|null $dedup_key
 * @property array<mixed> $payload
 * @property string $error
 * @property int $attempts
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $resolved_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AiDeadLetterEntry extends Model
{
    use BelongsToCompany;

    public const STATUS_OPEN = 'open';

    public const STATUS_REPLAYING = 'replaying';

    public const STATUS_RESOLVED = 'resolved';

    public const UPDATED_AT = null;

    protected $table = 'ai_dead_letter_queue';

    protected $fillable = [
        'company_id',
        'job_class',
        'job_id',
        'dedup_key',
        'payload',
        'error',
        'attempts',
        'status',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'string',
            'job_id' => 'integer',
            'payload' => 'array',
            'attempts' => 'integer',
            'created_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
