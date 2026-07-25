<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-002 — A single message inside a {@see ConversationThread}.
 *
 * At most one attachment is allowed per message ("pieces jointes
 * limitees" acceptance criterion); the attachment is stored on the
 * private `local` disk and only ever served back to the two thread
 * participants through the API, never a public URL.
 *
 * This model intentionally has no `updated_at` column: messages are
 * immutable once posted (no edit/delete in scope for this ticket).
 *
 * @property int $id
 * @property string $company_id
 * @property int $conversation_thread_id
 * @property int $author_id
 * @property string $body
 * @property string|null $attachment_path
 * @property string|null $attachment_original_name
 * @property string|null $attachment_mime_type
 * @property int|null $attachment_size
 * @property Carbon $created_at
 * @property-read ConversationThread|null $thread
 * @property-read Employee|null $author
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class ConversationMessage extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'conversation_thread_id',
        'author_id',
        'body',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime_type',
        'attachment_size',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<ConversationThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ConversationThread::class, 'conversation_thread_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }
}
