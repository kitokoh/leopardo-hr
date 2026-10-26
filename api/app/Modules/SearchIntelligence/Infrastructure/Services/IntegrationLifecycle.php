<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\SearchIntelligence\Domain\Enums\IntegrationStatus;
use App\Modules\SearchIntelligence\Domain\Models\Integration;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * GSI-8 (#8519) — cycle de vie des connecteurs externes (GSC, GA4, Google Ads,
 * MS Ads).
 *
 * Trois garanties portées ici, et une seule fois :
 *
 * 1. **Le secret ne sort jamais.** Les identifiants sont écrits via le cast
 *    `encrypted` du modèle (AES au repos, clé applicative) et ne sont JAMAIS
 *    renvoyés par l'API — seule la projection `toPublicArray()` est exposée, et
 *    elle ne contient qu'un booléen `has_credentials`.
 *
 * 2. **L'état reste honnête.** `POST /test` ne peut pas prouver à lui seul
 *    qu'un jeton fonctionne : il n'appelle pas les API Google/Microsoft (aucun
 *    client HTTP n'est branché, #8523 point 1). Un test réussi laisse donc le
 *    connecteur en `pending_authorization` — JAMAIS en `connected`. Prétendre
 *    le contraire afficherait « connecté » sur un écran où aucune donnée
 *    n'arrive, ce que l'issue interdit explicitement.
 *
 * 3. **Toute mutation est auditée**, avec l'acteur plateforme réel et sans
 *    jamais journaliser le secret (action + provider + empreinte seulement).
 */
final class IntegrationLifecycle
{
    /**
     * Fournisseurs supportés — liste fermée, alignée sur la contrainte de la
     * table `integrations` et sur l'écran (4 cartes attendues).
     *
     * @var list<string>
     */
    public const PROVIDERS = ['gsc', 'ga4', 'google_ads', 'ms_ads'];

    /**
     * Clés acceptées dans un jeu d'identifiants, par fournisseur.
     *
     * On ne stocke PAS un blob arbitraire envoyé par le client : une clé
     * inconnue est rejetée. Cela évite qu'un champ mal nommé (`apiKey` au lieu
     * de `api_key`) soit silencieusement persisté puis ignoré à la synchro, et
     * qu'une valeur inattendue (jeton d'un autre service, PII) se retrouve
     * chiffrée dans une colonne dont personne ne connaît le contenu.
     *
     * @var array<string, list<string>>
     */
    private const CREDENTIAL_KEYS = [
        'gsc' => ['client_id', 'client_secret', 'refresh_token', 'site_url'],
        'ga4' => ['client_id', 'client_secret', 'refresh_token', 'property_id'],
        'google_ads' => ['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id'],
        'ms_ads' => ['client_id', 'client_secret', 'refresh_token', 'customer_id'],
    ];

    public static function isSupportedProvider(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    /**
     * @return list<string>
     */
    public static function credentialKeysFor(string $provider): array
    {
        return self::CREDENTIAL_KEYS[$provider] ?? [];
    }

    /**
     * Enregistre (ou remplace) les identifiants d'un fournisseur.
     *
     * Remplacement, jamais fusion : un jeu d'identifiants partiel mélangé à
     * l'ancien produirait un connecteur qui « a l'air » configuré mais dont le
     * `refresh_token` vient d'une autre organisation — panne silencieuse à la
     * première synchro. L'appelant doit donc envoyer le jeu complet.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function configure(string $provider, array $credentials, ?int $actorId): Integration
    {
        $this->assertProvider($provider);

        $normalized = $this->normalizeCredentials($provider, $credentials);

        return DB::transaction(function () use ($provider, $normalized, $actorId): Integration {
            /** @var Integration $integration */
            $integration = Integration::query()->firstOrNew(['provider' => $provider]);
            $wasConfigured = $integration->exists;

            $previousStatus = $integration->status ?? IntegrationStatus::NotConnected;

            $integration->provider = $provider;
            // Configurer ne connecte pas : il faut d'abord tester. On retombe
            // sur un état qui dit « en attente d'autorisation » plutôt que de
            // conserver un `connected` hérité d'un ancien jeu d'identifiants
            // désormais remplacé.
            $integration->status = IntegrationStatus::NotConnected;
            $integration->credentials_encrypted = json_encode($normalized, JSON_THROW_ON_ERROR);
            $integration->last_error = null;
            $integration->save();

            $this->audit(
                action: $wasConfigured ? 'search_intel.integration.reconfigured' : 'search_intel.integration.configured',
                integration: $integration,
                actorId: $actorId,
                old: ['status' => $previousStatus->value],
                new: [
                    'status' => $integration->status->value,
                    // JAMAIS de valeur : uniquement les clés fournies et une
                    // empreinte, pour prouver le remplacement sans fuiter.
                    'credential_keys' => array_keys($normalized),
                    'credentials_fingerprint' => $this->fingerprint($normalized),
                ],
            );

            return $integration->refresh();
        });
    }

    /**
     * « Tester » un connecteur, honnêtement.
     *
     * Sans appel réel aux API du fournisseur, on ne peut que vérifier la
     * présence des champs requis. Le résultat est donc `pending_authorization`
     * en cas de succès — jamais `connected`. Un échec de validation est un
     * `error` motivé.
     *
     * @return array{status: IntegrationStatus, message: string}
     */
    public function test(string $provider, ?int $actorId): array
    {
        $this->assertProvider($provider);

        /** @var Integration|null $integration */
        $integration = Integration::query()->where('provider', $provider)->first();

        if ($integration === null || ! $this->hasStoredCredentials($integration)) {
            $integration ??= new Integration(['provider' => $provider]);
            $integration->status = IntegrationStatus::NotConnected;
            $integration->last_error = 'Aucun identifiant enregistré : configurez le connecteur avant de le tester.';
            $integration->save();

            $this->audit('search_intel.integration.test_failed', $integration, $actorId, [], [
                'reason' => 'no_credentials',
            ]);

            return [
                'status' => IntegrationStatus::NotConnected,
                'message' => 'Aucun identifiant enregistré : configurez le connecteur avant de le tester.',
            ];
        }

        $decoded = $this->decodeCredentials($integration);
        $missing = array_values(array_diff(
            self::credentialKeysFor($provider),
            array_keys($decoded),
        ));

        if ($missing !== []) {
            $integration->status = IntegrationStatus::Error;
            $integration->last_error = 'Identifiants incomplets : '.implode(', ', $missing).'.';
            $integration->save();

            $this->audit('search_intel.integration.test_failed', $integration, $actorId, [], [
                'reason' => 'incomplete_credentials',
                'missing' => $missing,
            ]);

            return [
                'status' => IntegrationStatus::Error,
                'message' => (string) $integration->last_error,
            ];
        }

        // Validation locale OK. Aucun appel réseau : l'autorisation réelle
        // (consentement OAuth, accès à la propriété) n'est pas prouvée.
        $integration->status = IntegrationStatus::NotConnected;
        $integration->last_error = 'Identifiants complets : autorisation à finaliser auprès du fournisseur.';
        $integration->save();

        $this->audit('search_intel.integration.tested', $integration, $actorId, [], [
            'outcome' => 'pending_authorization',
        ]);

        return [
            'status' => IntegrationStatus::NotConnected,
            'message' => (string) $integration->last_error,
        ];
    }

    /**
     * Révoque un connecteur : les identifiants sont effacés, pas seulement
     * marqués. Conserver un secret après une révocation serait une fuite
     * dormante.
     */
    public function revoke(string $provider, ?int $actorId): Integration
    {
        $this->assertProvider($provider);

        /** @var Integration $integration */
        $integration = Integration::query()->firstOrNew(['provider' => $provider]);

        $previousStatus = $integration->status ?? IntegrationStatus::NotConnected;
        $hadCredentials = $this->hasStoredCredentials($integration);

        $integration->provider = $provider;
        $integration->status = IntegrationStatus::NotConnected;
        $integration->credentials_encrypted = null;
        $integration->last_error = null;
        $integration->save();

        $this->audit('search_intel.integration.revoked', $integration, $actorId, [
            'status' => $previousStatus->value,
            'had_credentials' => $hadCredentials,
        ], [
            'status' => $integration->status->value,
            'had_credentials' => false,
        ]);

        return $integration->refresh();
    }

    /**
     * Projection publique d'une intégration : jamais le secret.
     *
     * @return array{provider: string, status: string, status_label: string, has_credentials: bool, last_synced_at: string|null, last_error: string|null}
     */
    public function toPublicArray(string $provider, ?Integration $integration): array
    {
        return [
            'provider' => $provider,
            'status' => $integration?->status->value ?? IntegrationStatus::NotConnected->value,
            'status_label' => ($integration->status ?? IntegrationStatus::NotConnected)->label(),
            'has_credentials' => $integration !== null && $this->hasStoredCredentials($integration),
            'last_synced_at' => $integration?->last_synced_at?->toIso8601String(),
            'last_error' => $integration?->last_error,
        ];
    }

    private function assertProvider(string $provider): void
    {
        if (! self::isSupportedProvider($provider)) {
            throw new InvalidArgumentException("Fournisseur non supporté : {$provider}.");
        }
    }

    /**
     * Ne garde que les clés connues, non vides, sous forme de chaînes.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, string>
     */
    private function normalizeCredentials(string $provider, array $credentials): array
    {
        $allowed = self::credentialKeysFor($provider);
        $normalized = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Clé d'identifiant inconnue pour {$provider} : {$key}."
                );
            }

            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException(
                    "La valeur de {$key} doit être une chaîne non vide."
                );
            }

            $normalized[$key] = trim($value);
        }

        return $normalized;
    }

    private function hasStoredCredentials(Integration $integration): bool
    {
        // `getAttributes()` renvoie la valeur BRUTE (non déchiffrée) et non
        // typée : c'est ce qu'il faut ici, puisqu'on veut savoir si une valeur
        // est stockée sans la déchiffrer (et sans qu'un cast ne la décode).
        $raw = $integration->getAttributes()['credentials_encrypted'] ?? null;

        return is_string($raw) && $raw !== '';
    }

    /**
     * @return array<string, string>
     */
    private function decodeCredentials(Integration $integration): array
    {
        if (! $this->hasStoredCredentials($integration)) {
            return [];
        }

        $decoded = json_decode((string) $integration->credentials_encrypted, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Empreinte non réversible d'un jeu d'identifiants, pour prouver un
     * remplacement dans l'audit sans jamais journaliser de valeur.
     *
     * @param  array<string, string>  $credentials
     */
    private function fingerprint(array $credentials): string
    {
        ksort($credentials);

        return substr(hash('sha256', json_encode($credentials, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(
        string $action,
        Integration $integration,
        ?int $actorId,
        array $old,
        array $new,
    ): void {
        AuditLog::query()->create([
            'company_id' => null,
            'user_id' => null,
            'action' => $action,
            'module' => 'search_intel',
            'auditable_type' => Integration::class,
            'auditable_id' => (string) $integration->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => ['actor_super_admin_id' => $actorId],
        ]);
    }
}
