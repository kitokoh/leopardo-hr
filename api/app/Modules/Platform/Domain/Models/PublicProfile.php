<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * COULOIR 16 — M3-bis · Profil public d'un client (schéma `public`).
 *
 * Table PLATEFORME (`public_profiles`) : les données du client vivent dans le
 * schéma `public`, **jamais dans un tenant** (règle d'architecture n°1 du
 * couloir). Un commerçant ne doit jamais pouvoir lire le profil d'un client.
 *
 * ## Ce que ce modèle porte, et ce qu'il ne porte pas
 *
 * Il porte la préférence de langue (`preferred_locale`), une ville
 * approximative en **libellé** (jamais une coordonnée), et le consentement aux
 * notifications avec sa date. Il ne porte ni téléphone, ni position, ni
 * adresse : le téléphone vit dans `users.phone_e164`, et la position n'est
 * jamais conservée.
 *
 * ## `preferred_locale` prime sur `users.preferred_language`
 *
 * `users.preferred_language` est en `varchar(2)` et ne peut pas distinguer un
 * arabe RTL d'un arabe latinisé. Pour la surface publique, c'est
 * `preferred_locale` qui fait foi ; `users.preferred_language` reste la valeur
 * par défaut à la création du profil.
 *
 * EXCEPTION TENANT-SCOPE (#7999) : table PLATEFORME sans appartenance tenant.
 *
 * @property int $user_id
 * @property string $preferred_locale
 * @property string|null $approx_city
 * @property string|null $approx_country
 * @property bool $notifications_opt_in
 * @property Carbon|null $notifications_consent_at
 * @property string|null $privacy_policy_version
 * @property Carbon|null $last_active_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PublicProfile extends Model
{
    protected $table = 'public_profiles';

    /** PK = FK vers `users` : pas d'auto-incrément. */
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'preferred_locale',
        'approx_city',
        'approx_country',
        'notifications_opt_in',
        'notifications_consent_at',
        'privacy_policy_version',
        'last_active_at',
    ];

    /**
     * Locales servies par le canal public. Source unique : aucune autre liste
     * de langues n'est écrite ailleurs dans le code du couloir.
     *
     * @var list<string>
     */
    public const SUPPORTED_LOCALES = ['fr', 'en', 'ar', 'tr'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifications_opt_in' => 'boolean',
            'notifications_consent_at' => 'datetime',
            'last_active_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
