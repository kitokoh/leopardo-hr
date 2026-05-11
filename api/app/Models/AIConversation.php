<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIConversation extends Model
{
    use BelongsToCompany;

    protected $table = 'ai_conversations';

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'messages',
        'context',
        'token_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'context' => 'array',
            'token_count' => 'integer',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id');
    }

    /** @return HasMany<AIAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AIAuditLog::class, 'conversation_id');
    }
}
