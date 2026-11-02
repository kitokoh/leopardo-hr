<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * COULOIR 16 — M3 (#9105) · Favori d'un client public.
 *
 * Table PLATEFORME (`public_favorites`, schéma `public`) : le favori appartient
 * au CLIENT, pas au commerçant. Un professionnel ne doit jamais pouvoir savoir
 * qui l'a mis en favori — c'est une donnée de la personne.
 *
 * Le favori stocke `(target_type, target_slug)` et **jamais** `company_id` : le
 * slug est l'identifiant public (même contrat que `/public/discover`), et
 * accepter un `company_id` fourni par le client permettrait de viser un tenant
 * arbitraire.
 *
 * EXCEPTION TENANT-SCOPE (#7999) : table PLATEFORME sans appartenance tenant.
 *
 * @property int $id
 * @property int $user_id
 * @property string $target_type
 * @property string $target_slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PublicFavorite extends Model
{
    protected $table = 'public_favorites';

    protected $fillable = [
        'user_id',
        'target_type',
        'target_slug',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
