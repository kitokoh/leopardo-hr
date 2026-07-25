<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PA2-COMM-012 — One message in a `PlatformSupportTicket` conversation
 * thread, authored either by the tenant employee who opened the ticket (or a
 * teammate replying on it) or by a platform super-admin. Exactly one of
 * `author_employee_id` / `author_super_admin_id` is set.
 *
 * @property int $id
 * @property int $platform_support_ticket_id
 * @property int|null $author_employee_id
 * @property int|null $author_super_admin_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property-read PlatformSupportTicket $ticket
 * @property-read Employee|null $authorEmployee
 * @property-read SuperAdmin|null $authorSuperAdmin
 *
 * @mixin Builder<static>
 */
class PlatformSupportMessage extends Model
{
    protected $table = 'platform_support_messages';

    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_support_ticket_id',
        'author_employee_id',
        'author_super_admin_id',
        'body',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<PlatformSupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PlatformSupportTicket::class, 'platform_support_ticket_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function authorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_employee_id');
    }

    /** @return BelongsTo<SuperAdmin, $this> */
    public function authorSuperAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'author_super_admin_id');
    }

    public function isFromPlatform(): bool
    {
        return $this->author_super_admin_id !== null;
    }
}
