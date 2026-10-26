<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Models;

use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * GSI-8 (#8519) — intégration externe (GSC, GA4, Google Ads, MS Ads).
 *
 * `NotConnected` est l'état par défaut : la vue d'ensemble peut donc afficher
 * « non connecté » honnêtement au lieu de zéros qui se liraient comme de
 * mauvais résultats.
 *
 * Les identifiants sont stockés CHIFFRÉS (`credentials_encrypted`, cast
 * `encrypted` de Laravel) et ne sont jamais sérialisés : `toArray()` retire la
 * colonne, et le modèle n'expose qu'un booléen `has_credentials`.
 *
 * @property int $id
 * @property string $provider
 * @property IntegrationStatus $status
 * @property string|null $credentials_encrypted
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 *
 * @mixin Builder<static>
 */
class Integration extends Model
{
    protected $table = 'integrations';

    protected $fillable = [
        'provider',
        'status',
        'credentials_encrypted',
        'last_synced_at',
        'last_error',
    ];

    /**
     * Le secret ne sort JAMAIS d'une sérialisation : ni dans une réponse JSON
     * d'API, ni dans un log de debug qui ferait un `json_encode($model)`.
     *
     * @var list<string>
     */
    protected $hidden = [
        'credentials_encrypted',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'credentials_encrypted' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
    }

    /** Un connecteur est-il utilisable (connecté ET porteur de secrets) ? */
    public function isUsable(): bool
    {
        return $this->status->canSync()
            && is_string($this->credentials_encrypted)
            && $this->credentials_encrypted !== '';
    }

    /** @param  Builder<self>  $query */
    public function scopeConnected(Builder $query): void
    {
        $query->where('status', IntegrationStatus::Connected->value);
    }
}
