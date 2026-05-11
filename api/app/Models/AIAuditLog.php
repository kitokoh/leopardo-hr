<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIAuditLog extends Model
{
    use BelongsToCompany;

    protected $table = 'ai_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'conversation_id',
        'prompt',
        'response',
        'tools_called',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_cents',
        'duration_ms',
        'error',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tools_called' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_cents' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AIConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AIConversation::class, 'conversation_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id');
    }
}
