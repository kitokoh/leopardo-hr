<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Profil public d'une officine (BC PharmaManager, #9592).
 *
 * Une seule ligne par tenant (`company_id` UNIQUE, create-or-update), même
 * patron que `RetailOnlineSettings` (BC-17).
 *
 * ## Rôle dans la vitrine
 *
 * C'est la **source de vérité** de la question « cette officine est-elle
 * publiable ? ». `PharmacyDiscoverableProvider` ne lit que cette table : il
 * n'existe aucune requête SQL parallèle qui pourrait publier une officine
 * non opt-in.
 *
 * ## Ce que ce modèle ne porte PAS
 *
 * Ni stock, ni lots, ni ordonnances, ni prix. La vitrine publique présente
 * l'officine (nom, ville, services, garde) ; elle ne vend pas de médicament —
 * la vente en ligne de médicaments est réglementée et n'est pas exposée ici.
 *
 * @property int $id
 * @property string $company_id
 * @property bool $enabled
 * @property string|null $public_slug
 * @property string|null $public_name
 * @property string|null $public_city
 * @property string|null $public_description
 * @property string|null $public_phone
 * @property bool $on_duty
 * @property list<string>|null $services
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PharmacyPublicProfile extends Model
{
    use BelongsToCompany;

    protected $table = 'pharmacy_public_profiles';

    protected $fillable = [
        'company_id',
        'enabled',
        'public_slug',
        'public_name',
        'public_city',
        'public_description',
        'public_phone',
        'on_duty',
        'services',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'on_duty' => 'boolean',
            'services' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * Vrai si l'officine est réellement publiable.
     *
     * Double barrière volontaire avec le `WHERE` du provider : la requête
     * protège le cas courant, cette règle protège tout appelant qui
     * construirait le DTO autrement. Un profil à moitié rempli n'est jamais
     * publié — fail-closed.
     */
    public function isPubliclyVisible(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        foreach ([$this->public_slug, $this->public_name] as $required) {
            if ($required === null || trim($required) === '') {
                return false;
            }
        }

        return true;
    }
}
