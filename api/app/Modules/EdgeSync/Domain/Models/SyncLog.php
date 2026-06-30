<?php

namespace App\Modules\EdgeSync\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit log for every sync operation between Edge ↔ Cloud.
 *
 * @property string $id
 * @property string $edge_node_id
 * @property string $direction    push|pull|bidirectional
 * @property string $status       pending|running|success|partial|failed
 * @property int    $records_sent
 * @property int    $records_received
 * @property int    $conflicts_detected
 * @property int    $conflicts_resolved
 * @property string|null $error_message
 * @property array<string,mixed> $summary
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SyncLog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'sync_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'edge_node_id', 'direction', 'status',
        'records_sent', 'records_received',
        'conflicts_detected', 'conflicts_resolved',
        'error_message', 'summary', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'summary'     => 'array',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function edgeNode(): BelongsTo
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if ($this->started_at && $this->finished_at) {
            return (int) $this->finished_at->diffInSeconds($this->started_at);
        }

        return null;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
