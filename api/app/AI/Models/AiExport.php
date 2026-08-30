<?php

declare(strict_types=1);

namespace App\AI\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BC-23-D07 (issue #6239) — exportation asynchrone d'une conversation IA.
 *
 * Cycle d'état : pending → processing → done | failed (puis replay via DLQ :
 * pending → …). Idempotence par `dedup_key` unique (tenant, conversation,
 * format) : rejouer la demande renvoie l'exportation existante.
 *
 * @property int $id
 * @property string $company_id
 * @property int $user_id
 * @property int $conversation_id
 * @property string $format
 * @property string $dedup_key
 * @property string $status
 * @property string|null $file_path
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class AiExport extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $table = 'ai_exports';

    protected $fillable = [
        'company_id',
        'user_id',
        'conversation_id',
        'format',
        'dedup_key',
        'status',
        'file_path',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'company_id' => 'string',
            'user_id' => 'integer',
            'conversation_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function dedupKey(string $companyId, int $conversationId, string $format): string
    {
        return "ai_export:{$companyId}:{$conversationId}:{$format}";
    }
}
