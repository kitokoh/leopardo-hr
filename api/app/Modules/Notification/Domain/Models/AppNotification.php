<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app notification entity.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array  $data
 * @property bool   $read
 * @property string|null $read_at
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read',
        'read_at',
        'action_url',
    ];

    protected $casts = [
        'data'    => 'array',
        'read'    => 'boolean',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Core\Auth\Domain\Models\User::class);
    }
}
