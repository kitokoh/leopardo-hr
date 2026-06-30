<?php

namespace App\Modules\EdgeSync\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Outbound queue – records waiting to be synced to Cloud.
 *
 * @property string $id
 * @property string $edge_node_id
 * @property string $entity_type   e.g. attendance_logs, absences
 * @property string $entity_id     UUID of the local record
 * @property string $operation     create|update|delete
 * @property array<string,mixed> $payload
 * @property string $status        pending|processing|synced|conflict|failed
 * @property int    $attempt_count
 * @property string|null $conflict_resolution  local_wins|cloud_wins|manual
 * @property string|null $conflict_note
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class SyncQueue extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'sync_queue';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'edge_node_id', 'entity_type', 'entity_id', 'operation',
        'payload', 'status', 'attempt_count',
        'conflict_resolution', 'conflict_note', 'synced_at',
    ];

    protected $casts = [
        'payload'   => 'array',
        'synced_at' => 'datetime',
    ];

    public function edgeNode(): BelongsTo
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isConflict(): bool
    {
        return $this->status === 'conflict';
    }

    public function shouldRetry(): bool
    {
        return $this->hasFailed() && $this->attempt_count < 5;
    }
}
