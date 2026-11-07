<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * COULOIR 16 — M3-bis (#9142) · Notification IN-APP du client public.
 *
 * Table PLATEFORME (`public_notifications`, schéma `public`) : le client
 * n'appartient à AUCUN tenant — c'est pourquoi il n'y a **ni scope
 * `BelongsToCompany`, ni `company_id`**. Le destinataire est `user_id` seul.
 *
 * EXCEPTION TENANT-SCOPE (#7999) : table PLATEFORME sans appartenance tenant.
 * Même régime que `PublicActivityIndex` (#9105), `PublicFavorite` (#9105) et
 * `PublicContactRequest` (#8760).
 *
 * ⚠️ **Aucun contenu métier, aucune donnée personnelle.** `data` ne porte que
 * des références (`vertical`, `activity_type`, `external_ref`, `thread_id`) —
 * les mêmes identifiants que `public_activity_index`, qui n'autorisent rien.
 * Une notification ne donne donc jamais accès à une commande : ouvrir
 * l'activité rejoue le contrôle d'accès côté tenant.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $title
 * @property string $body
 * @property array<string, mixed>|null $data
 * @property Carbon|null $read_at
 * @property string|null $dedupe_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PublicNotification extends Model
{
    /** Un commerçant a répondu sur un fil public. */
    public const TYPE_MERCHANT_REPLY = 'merchant_reply';

    /** Le statut d'une commande ou d'une réservation a changé. */
    public const TYPE_ACTIVITY_STATUS = 'activity_status';

    /** Types qu'un client peut recevoir. Sert de garde à l'écriture. */
    public const TYPES = [self::TYPE_MERCHANT_REPLY, self::TYPE_ACTIVITY_STATUS];

    protected $table = 'public_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
        'dedupe_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Non lue = pas encore de date de lecture.
     *
     * @param  Builder<static>  $q
     * @return Builder<static>
     */
    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
